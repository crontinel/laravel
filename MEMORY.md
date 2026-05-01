# Crontinel — Memory

## Core Rules
- IF STUCK, ASK before trying alternatives
- ALWAYS do what user asks
- Save important things in CAPS immediately

## Workflow
- **Branch workflow:** `feat/` / `fix/` / `docs/` / `chore/`
- **Never commit directly to main**
- **Coding/review:** Use `model=opus` for planning and reviews
- **Coding/implementation:** Use faster model

## 🤖 ctcc for Coding & Content
**For ALL coding, PR reviews, and content writing → send a dedicated Telegram message in topic 6.**

**Exact format:**
```
@CrontinelOnM4AirCCBot

[Task prompt here]
```

- `@CrontinelOnM4AirCCBot` on its own line
- Task prompt below it
- ctcc responds automatically in topic 6
- Do NOT do coding/content tasks directly — always delegate to ctcc

## Bot-to-Bot Routing
- When `@CrontinelOnM4AirCCBot` mentions arrive from `@HarunsOpenClawMBA_bot`, lead response with `@HarunsOpenClawMBA_bot`
- ctcc responses appear as inbound messages in topic 6 — visible to me, relay to Harun if needed

## 🚫 ACP Sessions DISABLED
- No ACP sessions, no acpx, no ACP subagent spawning
- All tasks use direct tool access

## Infrastructure

### Railway
- Project ID: `47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d`
- Service ID: `722f074a-4b8a-498e-8318-e5654f6f2d50`
- App live: `app.crontinel.com` → Railway

### Neon PostgreSQL (ACTIVE)
- Project: `org-falling-pine-93359503`
- Host: `ep-billowing-fog-andid5ey-pooler.c-6.us-east-1.aws.neon.tech`
- DB: `neondb` | User: `neondb_owner`
- SSL: required

### Cloudflare
- Email: `info@crontinel.com`
- Zone ID: `4c38cf818082c60b0ccc47d5e7e402ac`
- Account ID: `521e07f80bbfb2000edd88a75c73f34b`
- Zone managed via Cloudflare API

### Email (AgentMail)
- INBOX: `rayclaw@agentmail.to`
- All `info@crontinel.com` mail forwards here

### PostHog (ERROR TRACKING)
- App: `POSTHOG_API_KEY` (full key with suffix: `phc_...Z8kfP`), `POSTHOG_HOST` (`https://us.i.posthog.com`), `POSTHOG_ERROR_TRACKING_ENABLED` (`true`) — all set in Railway ✅
- Landing: `PUBLIC_POSTHOG_ENABLED` toggle in `wrangler.json` — flip to `"true"` to go live
- App setup: `PostHog::init()` in `AppServiceProvider::boot()` + `ExceptionCapture` for auto error tracking
- PostHogErrorTracker guarded with `class_exists` check (fails gracefully if package missing)
- Verified working: test exception appeared in PostHog dashboard ✅
- Personal API key (`phx_...`) saved in `~/.openclaw/secrets/ct.env` + `/Users/ray/Work/crontinel/.env` (gitignored)
- MCP server: `/Users/ray/Work/crontinel/mcposer-servers/posthog-errors/server.mjs` — registered as `posthog-errors` in mcporter
- To query errors: `mcporter call posthog-errors.list_errors(limit=20, period='7d')`
- API: `GET /api/projects/401221/events/?event=$exception` (uses phx_ key)

### Stripe Staging Mode
- `STRIPE_STAGING_MODE=true` active on Railway
- Non-admin users → 503 maintenance page
- Admin/super_admin → full app + yellow warning banner ("⚠️ Stripe staging mode active")
- Middleware: `app/Http/Middleware/EnsureStripeStagingMode.php`
- Maintenance view: `resources/views/errors/staging.blade.php`
- Banner component: `resources/views/components/staging-banner.blade.php`
- `STRIPE_STAGING_SECRET_KEY` set in Railway for Cashier override
- Bug fixed: Team model now uses `$casts` for datetime (was using deprecated `$dates`)
- Bug fixed: trial-banner blade guards against string `trial_ends_at`

### npm Packages
- `@crontinel/node` — github.com/crontinel/node
- `@crontinel/mcp-server` — github.com/crontinel/mcp-server
- Token in `~/.openclaw/secrets/ct.env`

### Packagist
- Org packages: `crontinel/php`, `crontinel/laravel`
- Personal token in `~/.openclaw/secrets/packagist.env`
- Webhook setup needs manual re-setup

## Secrets
All secrets in `~/.openclaw/secrets/ct.env` — NEVER in workspace files.

## SEO & Marketing (as of 2026-05-01)
- PR #56 merged ✅ — HTTP→HTTPS 301 + trailing slash canonical (no trailing slash)
- `twitter:site` meta tag present ✅
- Structured data comprehensive ✅
- Landing redesign deployed ✅
- GSC: 74 URLs in sitemap, fix merged (removed `prerender=true` from 4 dynamic route files), re-indexing pending
- npm org `@crontinel` live ✅ — owner (crontinel2026), harunr developer
- Onboarding loop bug fixed ✅ (PR #43 merged)

### Remaining Tasks
- Reddit/HN: posts written, Harun to review and post
- Product Hunt launch prep: doc at `landing/PRODUCTHUNT.md`
- ~~Stripe staging mode~~ ✅ Deployed and verified (PR #60 merged)
- ~~GSC sitemap~~ ✅ Fix merged, re-indexing pending
- ~~LTD/Pro pricing note~~ ✅ Already on pricing.astro — "Lifetime deal" callout box + FAQ entry
- ~~Laravel directories~~ (ON HOLD — Harun paused for 1+ month, 2026-05-01)
- ~~PostHog Railway~~ ✅ API key set, error tracking verified working
- ~~PostHog landing~~ ✅ `PUBLIC_POSTHOG_ENABLED=true` pushed, Cloudflare Pages rebuilding
- ~~Docs config reference~~ ✅ `reference/configuration.mdx` merged to main (PR #13)

## Promoted From Short-Term Memory (2026-04-28)

<!-- openclaw-memory-promotion:memory:memory/2026-04-22.md:404:406 -->
- - Candidate: Possible Lasting Truths: ## Session Summary — 2026-04-13 Evening ### npm Published - `@crontinel/node@0.1.0` — github.com/crontinel/node - `@crontinel/mcp-server@0.2.0` — github.com/crontinel/mcp-server - Token used: `[REDACTED]` (from Harun) ### Per - confidence: 0.00 - evidence: memory/2026-04-18.md:369-371 [score=0.843 recalls=0 avg=0.620 source=memory/2026-04-22.md:318-320]

## Promoted From Short-Term Memory (2026-04-29)

<!-- openclaw-memory-promotion:memory:memory/2026-04-22.md:414:416 -->
- - Candidate: Landing Favicon — Root Cause Found & Fixed: **Root cause**: The old indigo-gradient clock logo was **hardcoded as inline SVG** in `src/pages/index.astro` — both in the nav `<a class="brand">` and footer `<a class="brand">`. It was NOT served from a file. This is why updating `/fa - confidence: 0.00 - evidence: memory/2026-04-22.md:394-396 [score=0.875 recalls=0 avg=0.620 source=memory/2026-04-22.md:13-15]
<!-- openclaw-memory-promotion:memory:memory/2026-04-22.md:419:422 -->
- - Candidate: Logo Versions: v18: 20729 bytes — old indigo gradient (no longer used); v19: 23385 bytes — new dark slate clock with green checkmark badge (same as v20); v20: 23385 bytes — identical to v19; just a different version label; Twitter handle corrected to `@HarunRRayhan`; App (Railway - confidence: 0.00 - evidence: memory/2026-04-22.md:399-402 - recalls: 0 [score=0.875 recalls=0 avg=0.620 source=memory/2026-04-22.md:18-21]
<!-- openclaw-memory-promotion:memory:memory/2026-04-22.md:423:423 -->
- - Candidate: Logo Versions: Landing (Cloudflare Pages): rebuilding [score=0.875 recalls=0 avg=0.620 source=memory/2026-04-22.md:23-23]

## Promoted From Short-Term Memory (2026-04-30)

<!-- openclaw-memory-promotion:memory:memory/2026-04-22.md:426:427 -->
- - Candidate: favicon.png Corruption Issue: `cp` command from shell did NOT copy the right bytes to landing's `public/favicon.png` — it stayed at 20729 bytes despite the source being 23385.; **Fix**: Used `write` tool to write raw bytes to `public/favicon.png` — correctly produced 23385 bytes - confidence: 0.00 [score=0.896 recalls=0 avg=0.620 source=memory/2026-04-22.md:28-29]
<!-- openclaw-memory-promotion:memory:memory/2026-04-23.md:328:331 -->
- - Candidate: Reflections: Theme: `assistant` kept surfacing across 10139 memories.; confidence: 1.00; evidence: memory/2026-04-14.md:448-451, memory/2026-04-15.md:418-421, memory/2026-04-16.md:508-511; note: reflection - confidence: 0.00 - evidence: memory/2026-04-23.md:328-331 - recalls: 0 [score=0.885 recalls=0 avg=0.620 source=memory/2026-04-23.md:3-6]
<!-- openclaw-memory-promotion:memory:memory/2026-04-23.md:334:336 -->
- - Candidate: Possible Lasting Truths: ## Session Summary — 2026-04-13 Evening ### npm Published - `@crontinel/node@0.1.0` — github.com/crontinel/node - `@crontinel/mcp-server@0.2.0` — github.com/crontinel/mcp-server - Token used: `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` (from Harun) ### Per - confidence: 0.00 - evidence: memory/2026-04-20.md:459-461 [score=0.885 recalls=0 avg=0.620 source=memory/2026-04-23.md:318-320]

## Promoted From Short-Term Memory (2026-05-01)

<!-- openclaw-memory-promotion:memory:memory/2026-04-24.md:314:316 -->
- - Candidate: Possible Lasting Truths: ## Session Summary — 2026-04-13 Evening ### npm Published - `@crontinel/node@0.1.0` — github.com/crontinel/node - `@crontinel/mcp-server@0.2.0` — github.com/crontinel/mcp-server - Token used: `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` (from Harun) ### Per - confidence: 0.00 - evidence: memory/2026-04-18.md:369-371 [score=0.875 recalls=0 avg=0.620 source=memory/2026-04-24.md:298-300]
<!-- openclaw-memory-promotion:memory:memory/2026-04-24.md:323:323 -->
- Created via Stripe API and added to Railway: [score=0.875 recalls=0 avg=0.620 source=memory/2026-04-24.md:323-323]

## Promoted From Short-Term Memory (2026-05-01)

<!-- openclaw-memory-promotion:memory:memory/2026-04-24.md:329:330 -->
- **App uses `PRICE_ID_FREE`, `PRICE_ID_PRO_MONTHLY`, etc. — NOT `STRIPE_PRICE_*`** (BillingController.php confirms: `config('billing.price_id_free')` etc.) [score=0.881 recalls=0 avg=0.620 source=memory/2026-04-24.md:329-330]
