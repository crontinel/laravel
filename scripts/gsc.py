#!/usr/bin/env python3
"""GSC tool - check indexing, submit URLs, get canonical errors."""
import json, sys
from google.auth import load_credentials_from_file
from google.auth.transport.requests import Request
import urllib.request, urllib.parse

CREDS_FILE = '/Users/ray/Work/crontinel/.secrets/gsc-service-account.json'
SITE = 'sc-domain:crontinel.com'
SCOPES = ['https://www.googleapis.com/auth/webmasters']

def get_token():
    creds, _ = load_credentials_from_file(CREDS_FILE, scopes=SCOPES)
    creds.refresh(Request())
    return creds.token

def api(path, token, data=None):
    url = f'https://www.googleapis.com/webmasters/v3/{path}'
    req = urllib.request.Request(url, data=data, headers={'Authorization': f'Bearer {token}'})
    if data: req.add_header('Content-Type', 'application/json')
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read().decode())

token = get_token()

cmd = sys.argv[1] if len(sys.argv) > 1 else 'status'

if cmd == 'status':
    r = api(f'sites/{SITE}', token)
    print(f"Site: {r['siteUrl']}")
    print(f"Permission: {r['permissionLevel']}")

elif cmd == 'submit':
    url = sys.argv[2] if len(sys.argv) > 2 else input('URL to submit: ')
    body = json.dumps({'url': url}).encode()
    r = api(f'sites/{SITE}/urlInspection/index:inspect', token, body)
    print(json.dumps(r, indent=2))

elif cmd == 'sitemaps':
    r = api(f'sites/{SITE}/sitemaps', token)
    for s in r.get('sitemap', []):
        print(f"  {s['path']} - {s.get('lastSubmitted','?')}")

else:
    print(f"Unknown command: {cmd}")
    print("Usage: gsc.py [status|submit|sitemaps]")
