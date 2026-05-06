# Crontinel Status Page Setup

**Updated:** 2026-05-06

## Self-Hosted Gatus Status Page

Self-hosted status page using Gatus. Configs in place:
- `~/Work/crontinel/status/config.yaml` — monitoring endpoints
- `~/Work/crontinel/status-page-tmp/config.yaml` — status page display config

**Infrastructure:** Pending decision (Hetzner VPS / Railway / Cloudflare Workers)

## Monitoring

Health check cron running every 15 min via Railway GraphQL (job `db16773e7f4e`):
- Checks `app.crontinel.com` and `crontinel.com`
- Auto-redeploys/restarts Railway service if either is down

## Custom Domain (status.crontinel.com)

Custom domain setup pending infrastructure decision. Three options:

1. **Hetzner VPS** — deploy Gatus on self-managed VPS (option 3 from original plan)
2. **Railway container** — run Gatus as a Railway service
3. **Cloudflare Workers** — lightweight Cloudflare-hosted option

## TODO

- [ ] Decide infrastructure: Hetzner VPS vs Railway vs Cloudflare Workers
- [ ] Deploy Gatus status page
- [ ] Configure status.crontinel.com custom domain
- [ ] Add app.crontinel.com to monitoring
