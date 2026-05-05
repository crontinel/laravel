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

## 🤖 Claude Code for Coding & Content
**For ALL coding, PR reviews, and content writing → use `claude -p` via terminal.**

**Workflow:**
1. **PLAN** — Start with `claude -p --model opus` for thorough analysis and planning
2. **IMPLEMENT** — Then use `claude -p --model sonnet` for faster implementation

**Rules:**
- Pass full context: file paths, current code, error messages, project conventions, constraints
- More context = better output. Don't be stingy with background info.
- Run from `~/Work/crontinel/app` for app work
- Inform Harun before using it each time
- ctcc (OpenClaw routing) is DISABLED — use direct `claude -p` instead

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
- Username: `HarunRay` (case-sensitive!)
- Token in `~/.openclaw/secrets/packagist.env`
- Webhook active on both repos — GitHub → Packagist sync working ✅
- Manual trigger: `curl -X POST https://packagist.org/api/github -H "Authorization: Bearer HarunRay:TOKEN" -d 'payload={"repository":{"url":"https://github.com/crontinel/php"}}'`

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
- Reddit/HN: posts written, Harun to review and post (SNOOZED until 2026-05-05)
- Product Hunt launch prep: doc at `landing/PRODUCTHUNT.md` (SNOOZED until 2026-05-05)
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

## Promoted From Short-Term Memory (2026-05-02)

<!-- openclaw-memory-promotion:memory:memory/2026-04-13.md:349:374 -->
- ### Non-Blocking Items Remaining - Laravel Magazine directory: needs account login to submit - `get.crontinel.com` DNS: doesn't exist, CLI install URL workaround applied - GSC: needs live site - PyPI: blocked on PyPI account creation - crates.io: GitHub OAuth only — no email/password possible - Packagist webhook: needs Packagist API token - docs/marketing/keyword-research.md — full keyword research (tools + keyword maps for WooCommerce accessibility, bulk edit, Shopify accessibility) - 9 free tools documented: Ubersuggest, Keyword Surfer, Keywords Everywhere, AnswerThePublic, Answer Socrates, Google Keyword Planner, GSC, AlsoAsked, ChatGPT - 80+ keywords mapped by intent: primary, purchase, long-tail, problem/pain, comparison - Content mapping: which keywords to target on which pages ## Session Summary — 2026-04-17 (Toolblip work) ### Laravel Test Fix (partial) - Added `bootstrap/providers.php` (Laravel 11 requires this explicitly) - Root cause: `files` binding missing in Laravel 11 + `configureRateLimiting()` called too early in `register()` instead of `boot()` - Fix pushed to `main`: `81b2df8` - Tests still failing (cache.store not bound) — deferred to tomorrow ### Frontend — Blog Listing Redesign - Card grid layout with gradient images per category (was list view) - Added `FeaturedImage` client component (with onError fallback to gradient) - Added `featuredImage` field to all 25 blog posts (gradient URLs via `api.radtx.com/gradient/{colors}`) - Added `generateStaticParams` to blog/[slug] → all 30 blog posts now pre-rendered [score=0.925 recalls=6 avg=0.499 source=memory/2026-04-13.md:349-374]

## Promoted From Short-Term Memory (2026-05-03)

<!-- openclaw-memory-promotion:memory:memory/2026-04-12.md:430:453 -->
- - Package: /Users/ray/Work/crontinel/ (crontinel/laravel — workspace root) - OSS: /Users/ray/Work/crontinel/oss/ (divergent, leave alone) - Subscribe API: https://crontinel.com/api/subscribe (Resend live) - Resend env vars: Cloudflare Worker secrets ### P0 blockers (still blocked) - Railway server + app.crontinel.com DNS - Stripe Price IDs + STRIPE_WEBHOOK_SECRET - App Resend SMTP - Packagist webhook for crontinel/php (needs API token) - @crontinel npm org + mcp-server publish - GitHub OAuth credentials - status.crontinel.com (Gatus on Railway) --- ## Sunday Evening Additions (2026-04-12, ~17:00 BST) ### Tasks Completed This Session - **www.crontinel.com redirect**: Landing uses Cloudflare Workers (NOT Pages) — `@astrojs/cloudflare` adapter with `output: server`. The `_redirects` file approach may not work for Workers. This needs review: either add a redirect in the worker middleware or handle via Cloudflare dashboard redirect rule. The commit was pushed but deployment behavior is TBD. - **Pricing page FAQ gaps**: Added 4 missing FAQ questions to pricing page: free tier limits, self-hosting, data safety, cancel anytime. Previously had 2, now has 6 total. Committed. - **All 20 docs.crontinel.com pages verified 200 OK** via curl. All internal cross-links also confirmed 200. - **MCP guide Pro+ requirement**: Already documented in `mcp/overview.md` and `mcp/tools.md` — confirmed present, no changes needed. [score=0.939 recalls=8 avg=0.481 source=memory/2026-04-12.md:430-453]
<!-- openclaw-memory-promotion:memory:memory/2026-04-13.md:303:304 -->
- - - `wire:poll.30000ms` on Livewire dashboard ✅ - Onboarding dark theme confirmed: `OnboardingLayout` uses `bg-slate-950` ✅ ### App Code (crontinel/app — separate git repo) Drafted but NOT committed (app/ is its own git repo): - `onboarding/app.blade.php`: full timezone list + search filter (was committed to `feat/profile-gdpr-welcome-polish` branch) - `onboarding/install.blade.php`: "Test connection" button after scheduler step - App code committed from `app/` directory ✅ (per earlier session) ### OSS Repo (crontinel/crontinel = oss/) - Divergent history conflict with crontinel/laravel — leaving alone per user's instruction ### P0 Blockers (remaining) 1. Server provisioning: AWS or Railway [confidence=0.58 evidence=memory/2026-04-12.md:274-304] - - status: staged - Candidate: Reflections: Theme: `18-21` kept surfacing across 27 memories.; confidence: 0.90; evidence: memory/2026-04-09.md:33-36, memory/2026-04-09.md:37-40, memory/2026-04-09.md:41-44; note: reflection - confidence: 0.00 - evidence: memory/2026-04-10.md:112-115 - recalls: 0 - status: staged - Candidate: Possible Lasting Truths: No strong candidate truths surfaced. - confidence: 0.00 - evidence: memory/2026-04-10.md:118-118 - recalls: 0 - status: staged - Candidate: Reflections: Theme: `26-29` kept surfacing across 24 memories.; confidence: 0.89; evidence: memory/2026-04-09.md:37-40, memory/2026-04-09.md:41-44, memory/2026-04-09.md:45-48; note: reflectio [confidence=0.54 evidence=memory/2026-04-10.md:22-47] [score=0.931 recalls=8 avg=0.494 source=memory/2026-04-13.md:303-304]
<!-- openclaw-memory-promotion:memory:memory/2026-04-25.md:331:331 -->
- **所有SDK的API base URL统一为 `https://app.crontinel.com/api`** [score=0.901 recalls=0 avg=0.620 source=memory/2026-04-25.md:331-331]

## Promoted From Short-Term Memory (2026-05-05)

<!-- openclaw-memory-promotion:memory:memory/2026-04-12.md:257:282 -->
- - `reference/configuration.md` still missing `config/crontinel.php` full example block (DOCS_LAUNCH_GAPS item CS-6, P1 — good task for manual pass) ### Package (crontinel/laravel) - SECURITY.md + README.md added ✅ - Smoke-tested `composer require crontinel/laravel` in clean Laravel 11 project → installs v0.3.0 cleanly ✅ - `crontinel:send-trial-nudge-emails` scheduled daily at 09:00 in `routes/console.php` ✅ - `PlanLimits::canUseAlerts()` enforced — free tier blocked from alert channels ✅ - `wire:poll.30000ms` on Livewire dashboard ✅ - Onboarding dark theme confirmed: `OnboardingLayout` uses `bg-slate-950` ✅ ### App Code (crontinel/app — separate git repo) Drafted but NOT committed (app/ is its own git repo): - `onboarding/app.blade.php`: full timezone list + search filter (was committed to `feat/profile-gdpr-welcome-polish` branch) - `onboarding/install.blade.php`: "Test connection" button after scheduler step - App code committed from `app/` directory ✅ (per earlier session) ### OSS Repo (crontinel/crontinel = oss/) - Divergent history conflict with crontinel/laravel — leaving alone per user's instruction ### P0 Blockers (remaining) 1. Server provisioning: AWS or Railway (manual — user does this) 2. DNS: `app.crontinel.com` → Railway server A record (manual) 3. Stripe Price IDs + `STRIPE_WEBHOOK_SECRET` (needs dashboard access) 4. App Resend SMTP → needs access to `app/.env` 5. Packagist webhook for `crontinel/php` → needs Packagist API token 6. `@crontinel` npm org + publish `mcp-server` → needs npm account/org access [score=0.911 recalls=6 avg=0.502 source=memory/2026-04-12.md:257-282]
<!-- openclaw-memory-promotion:memory:memory/2026-04-28.md:4:4 -->
- **How to invoke ctcc:** [score=0.881 recalls=0 avg=0.620 source=memory/2026-04-28.md:4-4]
<!-- openclaw-memory-promotion:memory:memory/2026-04-28.md:5:7 -->
- @CrontinelOnM4AirCCBot [score=0.881 recalls=0 avg=0.620 source=memory/2026-04-28.md:11-12]
<!-- openclaw-memory-promotion:memory:memory/2026-04-28.md:13:13 -->
- [task prompt here] [score=0.881 recalls=0 avg=0.620 source=memory/2026-04-28.md:13-13]
