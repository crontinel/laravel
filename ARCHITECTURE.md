# Crontinel — Architecture Decisions

**Last updated:** 2026-04-05
**Status:** Approved, pre-coding

---

## Product model

**Option C — OSS package + hosted SaaS (Sentry model)**

- Free, MIT-licensed Laravel package anyone can self-host
- Hosted SaaS at app.crontinel.com for teams who don't want to manage it
- OSS package = distribution engine (Packagist downloads → inbound signups)
- SaaS = revenue engine

---

## Domain structure

| Domain | Purpose |
|---|---|
| `crontinel.com` | Marketing / landing page |
| `app.crontinel.com` | Hosted SaaS dashboard |
| `docs.crontinel.com` | Documentation (future) |

---

## Pricing

| Tier | Price | Limits |
|---|---|---|
| **Free forever** | $0 | 1 app, 5 monitors, 7-day history |
| **Pro** | $19/mo | 5 apps, unlimited monitors, 90-day history, Slack/PagerDuty alerts, API access |
| **Team** | $49/mo | Unlimited apps + users, 1-year history, priority support, status page |

- New signup → 14-day Pro trial, no credit card required
- After trial → drops to Free unless upgraded
- Billing is per **team**, not per user
- No per-seat pricing (keeps it simple)

**Competitive anchor:** Cronitor $20/mo (20 monitors, generic). We give deeper Laravel-native insight at same price.

---

## Stack

### Landing page (`crontinel.com`)
- **Astro** + Tailwind CSS
- Deployed to **Cloudflare Pages** (free, static only — no SSR, no Worker memory issues)
- Email capture → Resend or Mailchimp

### SaaS app (`app.crontinel.com`)
- **Laravel 12** (PHP 8.2+)
- **Livewire 3** + Alpine.js — real-time dashboard, no JS build step
- **MySQL** (production) / SQLite (local dev)
- **Redis** — queues + cache
- **Laravel Horizon** — queue worker management (dogfooding)
- **Laravel Cashier** — Stripe billing
- **Laravel Breeze** — auth (email/password + social TBD)

### OSS package (`harunrrayhan/cron-sentinel` on Packagist)
- Pure Laravel package, no external dependencies beyond Laravel itself
- Self-contained Blade dashboard
- Works without the SaaS — fully functional standalone

---

## Infrastructure (minimal spend)

| Component | Choice | Cost/mo |
|---|---|---|
| VPS | Hetzner CX22 (2 vCPU, 4GB RAM) | ~€4 |
| Deploy / server mgmt | **Coolify** (self-hosted on same VPS) | $0 |
| Database | MySQL on VPS | $0 |
| Cache / queues | Redis on VPS | $0 |
| Transactional email | Resend (free tier: 3K/mo) | $0 |
| Landing page hosting | Cloudflare Pages | $0 |
| SSL | Let's Encrypt via Coolify | $0 |
| **Total** | | **~€4/mo** |

> Skip Laravel Forge ($12/mo). Coolify handles provisioning, deploys, SSL, env vars.
> Upgrade to Forge or a bigger server when revenue justifies it.

---

## Multi-tenancy model

```
users           id, name, email, password, ...
teams           id, name, slug, plan (free/pro/team)
team_user       team_id, user_id, role (owner/admin/member)
apps            id, team_id, name, slug, api_key, timezone
monitors        id, app_id, type (horizon/queue/cron), name, config JSON
monitor_events  id, monitor_id, status, payload JSON, occurred_at
alerts          id, app_id, channel (slack/mail/webhook), config JSON
subscriptions   (managed by Laravel Cashier — on teams table)
```

- **One team → many users** (devs collaborating on same account)
- **One team → many apps** (agency or founder with multiple products)
- Billing attaches to the **team**, not the user

---

## OSS vs SaaS feature split

| Feature | OSS | SaaS Free | SaaS Pro | SaaS Team |
|---|---|---|---|---|
| Horizon monitoring | ✅ | ✅ | ✅ | ✅ |
| Queue depth + failed jobs | ✅ | ✅ | ✅ | ✅ |
| Cron job tracking | ✅ | ✅ | ✅ | ✅ |
| Blade dashboard | ✅ | ✅ | ✅ | ✅ |
| History retention | Unlimited (own DB) | 7 days | 90 days | 1 year |
| Slack / PagerDuty alerts | ✅ | — | ✅ | ✅ |
| Multiple apps | Unlimited | 1 | 5 | Unlimited |
| Team members | N/A | 1 | 3 | Unlimited |
| REST API | ✅ | — | ✅ | ✅ |
| Status page | — | — | ✅ | ✅ |
| Priority support | — | — | — | ✅ |

---

## Integration model (how user's app talks to SaaS)

**Agent-based (pull + push hybrid):**

1. User installs the OSS package in their Laravel app
2. Package sends heartbeats + events to `app.crontinel.com` API via their `api_key`
3. SaaS stores and visualizes the data
4. No polling required from SaaS side — app pushes on events (job failed, cron ran, etc.)
5. Fallback: SaaS pings a `/health` endpoint on the user's app every N minutes (optional)

This means the OSS package serves double duty: standalone self-hosted dashboard AND the agent for the SaaS.

---

## Build order (sequencing)

1. **Landing page** (`crontinel.com`) — coming soon + email capture — 1 day
2. **OSS package MVP** — Horizon + Queue + Cron monitors, Blade dashboard, alerts — 2–3 weeks
3. **SaaS auth + teams** — Breeze, team creation, app management — 1 week
4. **SaaS agent integration** — package pushes to SaaS API — 1 week
5. **Billing** — Cashier + Stripe, Free/Pro/Team plans — 1 week
6. **Polish + launch** — status page, docs, Packagist submit — 1 week

**Total: ~6–8 weeks to full launch**

---

## Still needs research / decision

- [ ] Stripe pricing page design (annual discount? yes/no)
- [ ] Email provider beyond Resend free tier (when >3K/mo)
- [ ] Docs site stack (Astro Starlight vs Mintlify)
- [ ] Status page: build in-house or embed Instatus/Betteruptime free tier
- [ ] Social login (GitHub OAuth makes sense for dev tool — research UX impact)
- [ ] ploy.cloud CF Pages memory fix (check build logs when CF access available)
