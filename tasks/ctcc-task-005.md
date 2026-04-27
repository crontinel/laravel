# Task: GSC Sitemap Indexing Push

## Context
Crontinel has 32 blog posts and multiple VS/use-case pages that need to be indexed in Google Search Console. The GSC service account is at `~/.openclaw/secrets/gsc-service-account.json` (per TOOLS.md: `~/Work/crontinel/.secrets/gsc-service-account.json`).

The sitemap is at `https://crontinel.com/sitemap.xml` and gets generated at build time.

## Tasks

### 1. Discover all pages
Fetch `https://crontinel.com/sitemap.xml` and parse all URLs listed. Also check the programmatic SEO queue at `landing/pseo-queue.json` and `landing/gsc-queue.json` for any pending pages.

### 2. Submit sitemap to GSC
Use the GSC API (via `googleapiclient.discovery` or direct HTTP) to:
- Submit the sitemap for crontinel.com
- Check which sitemaps are already submitted

### 3. Submit key pages for indexing
Submit batches of important pages for indexing:
- All blog posts
- All /vs/ comparison pages
- All /use-cases/ pages
- Homepage, /pricing, /features, /about

Use GSC URL Inspection API to request indexing for each URL.

### 4. Check for crawl errors
Check the GSC API for any crawl errors (404s, server errors, etc.) and report them.

## GSC API Setup
Use the service account JSON at `~/.openclaw/secrets/gsc-service-account.json` with the webmasters scope:
```
https://www.googleapis.com/auth/webmasters
```

Python approach:
```python
from google.auth import load_credentials_from_file
from google.auth.transport.requests import Request
from googleapiclient.discovery import build

creds, _ = load_credentials_from_file('/Users/ray/Work/crontinel/.secrets/gsc-service-account.json', scopes=['https://www.googleapis.com/auth/webmasters'])
creds.refresh(Request())
service = build('searchconsole', 'v1', credentials=creds)
```

## Working directory
`/Users/ray/Work/crontinel/`

## Output
Report:
1. How many URLs found in sitemap
2. How many were already submitted vs newly submitted
3. Any crawl errors found
4. Any pages that failed to submit
