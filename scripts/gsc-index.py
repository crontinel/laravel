#!/usr/bin/env python3
"""GSC Indexing Push for Crontinel"""
import warnings
warnings.filterwarnings('ignore')

from google.auth import load_credentials_from_file
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
import urllib.request
import xml.etree.ElementTree as ET
import json
import time

SITE_URL = "https://crontinel.com"
CREDS_FILE = "/Users/ray/Work/crontinel/.secrets/gsc-service-account.json"
SITEMAP_URL = f"{SITE_URL}/sitemap.xml"

print("=== GSC Indexing Push for Crontinel ===\n")

# Auth
creds, _ = load_credentials_from_file(CREDS_FILE, scopes=['https://www.googleapis.com/auth/webmasters'])
creds.refresh(Request())
service = build('searchconsole', 'v1', credentials=creds)
print("✅ Authenticated to GSC API\n")

# 1. Fetch sitemap
print("1. Fetching sitemap...")
try:
    with urllib.request.urlopen(SITEMAP_URL, timeout=30) as r:
        sitemap_xml = r.read().decode()
    root = ET.fromstring(sitemap_xml)
    ns = {'sm': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
    urls = [loc.text for loc in root.findall('sm:url/sm:loc', ns)]
    print(f"   Found {len(urls)} URLs in sitemap")
except Exception as e:
    print(f"   ERROR fetching sitemap: {e}")
    urls = []

# Also load the gsc-queue.json and pseo-queue.json
try:
    with open('/Users/ray/Work/crontinel/landing/gsc-queue.json') as f:
        gsc_queue = json.load(f)
    print(f"   gsc-queue.json: {len(gsc_queue.get('submitted', []))} submitted, {len(gsc_queue.get('pending', []))} pending")
except:
    gsc_queue = {'submitted': [], 'pending': []}

try:
    with open('/Users/ray/Work/crontinel/landing/pseo-queue.json') as f:
        pseo_queue = json.load(f)
    print(f"   pseo-queue.json: {len(pseo_queue.get('pending', []))} pending")
except:
    pseo_queue = {'pending': []}

# 2. Check existing sitemaps in GSC
print("\n2. Checking GSC sitemaps...")
try:
    result = service.sitemaps().list(siteUrl=SITE_URL).execute()
    sitemaps = result.get('sitemap', [])
    existing = [s['path'] for s in sitemaps]
    print(f"   Already submitted: {len(existing)}")
    for s in sitemaps:
        print(f"   - {s['path']} (last: {s.get('lastSubmitted', 'N/A')})")
except Exception as e:
    print(f"   ERROR: {e}")
    existing = []

# 3. Submit sitemap
if SITEMAP_URL not in existing:
    print(f"\n3. Submitting sitemap {SITEMAP_URL}...")
    try:
        service.sitemaps().submit(siteUrl=SITE_URL, feedpath=SITEMAP_URL).execute()
        print("   ✅ Sitemap submitted")
    except Exception as e:
        print(f"   ERROR submitting sitemap: {e}")
else:
    print(f"\n3. Sitemap already submitted, skipping")

# 4. Identify priority pages
blog_urls = [u for u in urls if '/blog/' in u]
vs_urls = [u for u in urls if '/vs/' in u]
use_case_urls = [u for u in urls if '/use-cases/' in u]
main_urls = [u for u in urls if u == SITE_URL or u in [SITE_URL + p for p in ['/features/', '/pricing/', '/about/', '/docs/', '/integrations/']]]

priority_urls = main_urls + blog_urls + vs_urls + use_case_urls
print(f"\n4. Priority pages identified: {len(main_urls)} main, {len(blog_urls)} blog, {len(vs_urls)} vs, {len(use_case_urls)} use-cases")

# 5. Submit pages for indexing in batches
print(f"\n5. Submitting {len(priority_urls)} pages for indexing...")
BATCH = 10
success_count = 0
fail_count = 0
failed_urls = []

for i in range(0, len(priority_urls), BATCH):
    batch = priority_urls[i:i+BATCH]
    print(f"   Batch {i//BATCH + 1}/{(len(priority_urls)-1)//BATCH + 1}: {batch[0]} ... ({len(batch)} URLs)")
    for url in batch:
        try:
            service.urlNotifications().markAsReviewed(
                siteUrl=SITE_URL,
                body={
                    'urlNotificationMetadata': {
                        'latestUpdate': {
                            'url': url,
                            'type': 'URL_UPDATED'
                        }
                    }
                }
            ).execute()
            success_count += 1
        except Exception as e:
            # Try the regular URL inspection method
            try:
                service.urlInspection().index().inspect(
                    siteUrl=SITE_URL,
                    body={'inspectionUrl': url, 'languageCode': 'en-US'}
                ).execute()
                success_count += 1
            except:
                fail_count += 1
                failed_urls.append(url)
    time.sleep(0.5)

print(f"\n   Submitted: {success_count}, Failed: {fail_count}")
if failed_urls:
    print(f"   Failed URLs: {failed_urls[:10]}")

# 6. Check for crawl errors
print("\n6. Checking crawl errors (last 90 days)...")
try:
    result = service.searchanalytics().query(
        siteUrl=SITE_URL,
        body={
            'startDate': '2026-01-27',
            'endDate': '2026-04-27',
            'dimensions': ['page', 'searchAppearance'],
            'rowLimit': 20
        }
    ).execute()
    
    rows = result.get('rows', [])
    print(f"   Top pages by impressions (last 90 days):")
    for row in rows[:10]:
        keys = row.get('keys', [])
        page = keys[0] if keys else 'N/A'
        impressions = row.get('impressions', 0)
        clicks = row.get('clicks', 0)
        print(f"   - {page}: {impressions} impressions, {clicks} clicks")
except Exception as e:
    print(f"   Could not fetch search analytics: {e}")

# 7. Save updated queue
try:
    submitted = list(set(gsc_queue.get('submitted', []) + priority_urls[:100]))
    gsc_queue['submitted'] = submitted
    with open('/Users/ray/Work/crontinel/landing/gsc-queue.json', 'w') as f:
        json.dump(gsc_queue, f, indent=2)
    print(f"\n✅ Updated gsc-queue.json ({len(submitted)} total submitted)")
except Exception as e:
    print(f"\n⚠️  Could not update gsc-queue.json: {e}")

print("\n=== Done ===")
