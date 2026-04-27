# Crontinel Status Page Setup

**Created:** 2026-04-10
**Service:** Checkly (checklyhq.com)
**Account:** harun.b13@gmail.com (signed in via GitHub)
**Plan:** Team Trial (free tier)

## Status Page

- **Public URL:** https://0j1trofn.checkly-status-page.com
- **Status Page ID:** db2f86ed-d839-4e0d-9d5a-6e0d19eb1634
- **Name:** Crontinel Status

## Monitors

| Monitor | Type | URL | Status |
|---|---|---|---|
| DNS Monitor - crontinel.com | DNS | crontinel.com | Passing |
| URL Monitor - crontinel.com | URL | https://crontinel.com | Passing |
| URL Monitor - docs.crontinel.com | URL | https://docs.crontinel.com | Passing |

## Services

| Service | Linked to card |
|---|---|
| crontinel.com | Website & Docs |
| docs.crontinel.com | Website & Docs |

## Custom Domain (status.crontinel.com)

Custom domains for Checkly status pages require a paid plan. Options:

1. **Upgrade Checkly** to a paid plan that supports custom domains, then CNAME `status.crontinel.com` to their provided value
2. **Use Cloudflare redirect rule** to redirect `status.crontinel.com` to `0j1trofn.checkly-status-page.com`
3. **Switch to self-hosted Gatus** on Hetzner VPS when ready

For now, the status page is live at the Checkly-hosted URL above.

## TODO

- [ ] Decide: upgrade Checkly or use redirect for status.crontinel.com
- [ ] Link monitors to services (currently services exist but monitors are not formally linked to services in Checkly's service model)
- [ ] Add app.crontinel.com monitor once SaaS is live
