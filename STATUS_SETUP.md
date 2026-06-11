# Crontinel Status Page Setup

> **⚠️ DEPRECATED DOC — kept for historical reference only.**
>
> The Gatus-based status page described below is **no longer the runtime
> poller**. The Laravel app's `CheckStatusPages` command is the single
> source of truth for HTTP checks, and the public status page is served by
> Laravel at `GET /status/{slug}` (and `/status` for Crontinel's own infra).
> See `docs/STATUS_PAGE_GATUS_CLEANUP.md` (Phase 6 migration plan) and
> `docs/ct-status-page-unified.md` for the architecture and rollout.
>
> The `status/` and `status-page-tmp/` submodules at the repo root are
> preserved as local-dev scaffolding only. Their `config.yaml` files start
> with a `DEPRECATED` banner pointing readers to the Laravel poller. They
> should not be deployed, scheduled, or pointed at by DNS in production.

**Updated:** 2026-06-11 — historical context only.

## Historical context — Self-Hosted Gatus Status Page

Self-hosted status page using Gatus. Configs in place (kept as local-dev scaffolding only):
- `~/Work/crontinel/status/config.yaml` — monitoring endpoints (DEPRECATED, see header)
- `~/Work/crontinel/status-page-tmp/config.yaml` — status page display config (DEPRECATED, see header)

**Infrastructure:** Laravel poller + Railway Postgres. The standalone Gatus
Railway service is superseded — see `docs/merged-status-page-plan.md` Phase 2
and the cleanup plan above.

## Monitoring

Health checks run via Laravel's `CheckStatusPages` artisan command, scheduled
every minute by `schedule:work` under `supervisord` inside the Railway app
container. The `CrontinelStatusPageSeeder` seeds two endpoints for the
Crontinel status page:

- `app.crontinel.com/up` (interval 30s)
- `crontinel-production.up.railway.app/up` (interval 30s)

## Custom Domain (status.crontinel.com)

`status.crontinel.com` is served by the Laravel app (no separate Gatus
container). Cloudflare CNAME points at `crontinel-production.up.railway.app`,
and the app routes `/` and `/status/{slug}` to `StatusPageController`.

## Out of date — TODO list (legacy)

- [x] ~~Decide infrastructure: Hetzner VPS vs Railway vs Cloudflare Workers~~
  → Laravel poller won. Gatus is deprecated.
- [x] ~~Deploy Gatus status page~~ → not needed; Laravel does this.
- [x] ~~Configure status.crontinel.com custom domain~~ → live in Laravel.
- [x] ~~Add app.crontinel.com to monitoring~~ → seeded in
  `CrontinelStatusPageSeeder`.
