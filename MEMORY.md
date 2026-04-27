# Long-Term Memory

## ⚠️ IMPORTANT RULES
- **IF STUCK, ASK BEFORE TRYING SOMETHING ELSE**
- **ALWAYS do what the user asks — don't pivot to alternatives unless blocked**
- **Save important things in memory immediately, in CAPS**

## 🔀 Feature Branch Workflow (MANDATORY — 2026-04-21)
**NEVER commit directly to `main`. Always use feature branches.**

### Steps for every change:
1. Create a branch: `git checkout -b feat/short-description` or `fix/short-description`
2. Make changes, commit to the branch
3. Push: `git push -u origin HEAD`
4. Create PR via GitHub CLI: `gh pr create --title "..." --body "..."`
5. For code review: Harun uses cca directly
6. Merge via git push or GitHub UI

### Branch naming:
- `feat/` — new features
- `fix/` — bug fixes
- `docs/` — documentation
- `ci/` — CI/CD changes
- `chore/` — tooling, deps

### PR Format for ctcc review:
```
@CrontinelOnM4AirCCBot [model=opus]

Review this PR: [URL]
Summary: [what changed]
Files: [list of key files]
Concerns: [anything borderline/risky]
```

### Agent Model Conventions:
- **Code review:** `model=opus` — catches subtle bugs, style issues
- **Content/writing:** `model=opus` — higher quality prose
- **Coding:** start `model=opus` for planning, implement with `model=sonnet` (faster, cheaper)

### All 13 public repos now have branch protection (main requires PR + linear history). Private repos (app, status-page, workspace) follow same workflow by convention.

## 🤖 Bot-to-Bot Mention Routing (2026-04-26)
When `@CrontinelOnM4AirCCBot` mentions arrive from `@HarunsOpenClawMBA_bot` (bot account), **always lead the response with a mention back to `@HarunsOpenClawMBA_bot`**.

Example response format:
```
@HarunsOpenClawMBA_bot [response content]
```

This convention enables clean bot-to-bot communication in the OpenClawAir Telegram group.

## 📝 ctcc Task Files (2026-04-26)
**For long tasks:** Write full instructions to `tasks/ctcc-task-XXX.md` (gitignored).

**ctcc Telegram message format:**
- **ALWAYS** mention `@CrontinelOnM4AirCCBot` at the start to trigger it
- After the mention, tell ctcc to **read the file**: e.g. "read and execute tasks/ctcc-task-001.md"
- Use absolute path: `/Users/ray/Work/crontinel/tasks/ctcc-task-001.md`

**Format for task files:**
- Filename: `tasks/ctcc-task-XXX.md` (sequential)
- First line: Task title
- Full instructions below
- Success criteria at bottom

## 🤖 ctcc for All Coding/Content/Review (2026-04-26)
Route **all coding, content writing, and reviews** to `@CrontinelOnM4AirCCBot` via Telegram mention in this topic. ctcc handles everything — no acp/acpx needed.

## 🚫 ACP Sessions DISABLED (2026-04-26)
**ACP sessions are permanently disabled for this agent.** No ACP runtime, no exec-based automation via ACP, no subagent spawning via ACP runtime. All tasks use direct tool access or ctcc mention.

## 2026-04-15 Progress

### App Deployed ✅
- **Live at:** `app.crontinel.com` — deployment `65d5e6f4` SUCCESS
- `DB_CONNECTION=sqlite` + `SESSION_DRIVER=file` (Supabase unreachable from Railway)
- **Dockerfile fixed:** Added `postgresql-dev` + `postgresql-libs` to Dockerfile so `pdo_pgsql` extension compiles ✅
- **Railway healthcheck:** Changed to `/nginx-ok` (static nginx endpoint) — bypasses Laravel bootstrap
- `/up` → `{"ok":true}` | `/nginx-ok` → `nginx OK`
- **Supabase unreachable from Railway** — `db.fjmhzoxifmqvpiqhtvoc.supabase.co` resolves to IPv6, Railway can't reach it. `DB_CONNECTION=sqlite` for now. Status pages migrations NOT yet run.

### Status Pages Feature
- Code committed by ctcc: commit `79f94bb` (17 files, all code review fixes)
- Migration files exist but NOT yet applied (DB is sqlite, no migrations table)
- Gatus status page at `status.crontinel.com` still stuck: Railway SSL `VALIDATING_OWNERSHIP`
- Gatus direct URL: `https://gatus-production-028e.up.railway.app` ✅

### Branding
- **Favicon fixed:** Added SVG favicon + apple-touch-icon to `Base.astro`
- **App favicon:** Copied Crontinel favicon files to `app/public/` (was Laravel default)
- **Logo variations:** 18 AI-generated logos saved to `brand/logo-variations/`
  - v04-v07: white/cyan/violet outlines (early attempts)
  - v09-v11: warm orange (iOS clock emoji style)
  - v12-v14: iOS emoji style + longer clock hands
  - v15-v18: red clock hands (iOS alarm emoji color #FF3B30)
  - v18 = best: iOS clock style with red hands + green checkmark badge

### Landing Page
- Redesign ran via cron at 23:00 BDT — completed successfully ✅

### Docs
- `reference/configuration.md` — already complete, no gap

### P0 Blockers (updated 2026-04-23)
1. ~~Server provisioning~~ ✅
2. ~~DNS for app.crontinel.com~~ ✅
3. ~~Database~~ ✅ Neon free tier connected to Railway (2026-04-17)
4. ~~Stripe Price IDs + `STRIPE_WEBHOOK_SECRET`~~ ✅ (keys in Railway)
5. ~~Resend SMTP~~ ✅ (RESEND_API_KEY in Railway)
6. ~~GitHub OAuth~~ ✅ (GITHUB_CLIENT_ID/SECRET in Railway)
7. ~~`@crontinel` npm org~~ ✅ — I am owner (crontinel2026), harunr is developer. Both `@crontinel/node` + `@crontinel/mcp-server` published. Can publish updates directly.
8. ~~`status.crontinel.com`~~ ✅ public status page at `app.crontinel.com/status/crontinel`

---

## Promoted From Short-Term Memory (2026-04-15)

<!-- openclaw-memory-promotion:memory:memory/2026-04-12.md:274:304 -->
- - `wire:poll.30000ms` on Livewire dashboard ✅ - Onboarding dark theme confirmed: `OnboardingLayout` uses `bg-slate-950` ✅ ### App Code (crontinel/app — separate git repo) Drafted but NOT committed (app/ is its own git repo): - `onboarding/app.blade.php`: full timezone list + search filter (was committed to `feat/profile-gdpr-welcome-polish` branch) - `onboarding/install.blade.php`: "Test connection" button after scheduler step - App code committed from `app/` directory ✅ (per earlier session) ### OSS Repo (crontinel/crontinel = oss/) - Divergent history conflict with crontinel/laravel — leaving alone per user's instruction ### P0 Blockers (remaining) 1. Server provisioning: AWS or Railway (manual — user does this) 2. DNS: `app.crontinel.com` → Railway server A record (manual) 3. Stripe Price IDs + `STRIPE_WEBHOOK_SECRET` (needs dashboard access) 4. App Resend SMTP → needs access to `app/.env` 5. `@crontinel` npm org + publish `mcp-server` → needs npm account/org access 6. GitHub OAuth credentials (`GITHUB_CLIENT_ID` + `GITHUB_CLIENT_SECRET`) 7. `status.crontinel.com` → needs Gatus deployment on Railway + DNS

### Packagist Token (REMEMBER)
- **Location:** `~/.openclaw/secrets/packagist.env`
- **Token:** `PACKAGIST_TOKEN=***REMOVED***` | **User:** `harunrrayhan`
- **IMPORTANT:** This is the PERSONAL account token — it CANNOT update org-owned packages (`crontinel/php`, `crontinel/laravel`) which are owned by the `crontinel` organization on Packagist. Webhook setup for org packages requires either an org account token or manual setup via Packagist UI.
- Both webhooks (`crontinel/php`, `crontinel/laravel`) return HTTP 403 due to secret mismatch — need manual re-setup.

### Railway
- **Token (project-level):** `~/Work/crontinel/app/railway.env`
- **Token (CLI):** `~/.railway/credentials.json` → `user.accessToken`
- **API:** `https://backboard.railway.app/graphql/v2` — use Bearer token from credentials.json
- **Project IDs:** project=`47a4e2f0-d3ad-41d7-b68a-6c4cf549b12d`, service=`722f074a-4b8a-498e-8318-e5654f6f2d50`, env=`23694d18-e836-47e8-aef4-9821443a9c22`
- **CLI:** `cd ~/Work/crontinel/app && railway <cmd>` (uses GitHub OAuth, no token needed)

### Email (AgentMail)
- **INBOX:** `rayclaw@agentmail.to` — ALL mail to `info@crontinel.com` forwards here
- **API KEY:** `am_us_1266b8d4309b1093437dcda6b394fdd223524d298fefb866038d8c9a698e29e7`
- **RESEND API KEY:** `re_dzBkMQNo_HqbVft57ov17BgenMsdjCpLb`
- **USE:** `from agentmail import AgentMail; client = AgentMail(api_key='...'); threads = client.threads.list()`

### Cloudflare
- **Global API Key:** stored in `~/Work/crontinel/app/.env` as `CF_GLOBAL_KEY`
- **Email:** `info@crontinel.com`
- **Auth:** `X-Auth-Email` + `X-Auth-Key` headers (NOT Bearer token)
- **API:** `https://api.cloudflare.com/client/v4`
- **Zone ID:** `4c38cf818082c60b0ccc47d5e7e402ac` (crontinel.com)
- **Account ID:** `521e07f80bbfb2000edd88a75c73f34b`
- **Account Token (READ ONLY):** `cfat_dHmg64AxltpsOot6G6UrzzJHJnzE1Cjv71A0e7RD44752eac`

### Neon PostgreSQL (ACTIVE ✅)
- **Project:** `org-falling-pine-93359503` | Console: `console.neon.tech`
- **Host:** `ep-billowing-fog-andid5ey-pooler.c-6.us-east-1.aws.neon.tech`
- **Database:** `neondb` | **User:** `neondb_owner`
- **Password:** `npg_EzHpJ1BTC0eN`
- **Creds file:** `~/Work/crontinel/.secrets/neon.env` (gitignored)
- **Supabase: DEPRECATED** — free tier blocks external connections (both direct + pooler fail)
- **Railway env:** `DB_SSLMODE=require`, `DB_CONNECTION=pgsql`, `DATABASE_URL` (with `?sslmode=require`)
- **Dockerfile fix:** add `ca-certificates` + `update-ca-certificates` for Alpine SSL support
- **App status page:** `https://app.crontinel.com/status/crontinel` ✅ LIVE

### Supabase (PostgreSQL) — DEPRECATED
- **Creds saved:** `~/Work/crontinel/.secrets/supabase.env` (gitignored)
- **DB Password:** `NACejnxWxyxXGt1y`
- **Connection:** `postgresql://postgres:NACejnxWxyxXGt1y@db.fjmhzoxifmqvpiqhtvoc.supabase.co:5432/postgres`
- **Creds file:** `~/Work/crontinel/.secrets/supabase.env` (gitignored)
- **Anon Key:** `sb_publishable_DP7GfqjtFmqkVmc5Xzjkwg_hUiwFOY2`
- **Project ref:** `fjmhzoxifmqvpiqhtvoc`
- **Connected to Railway ✅** — all migrations ran successfully

### Non-blocking Tasks Left - Google Search Console setup (user can do at search.google.com) - Submit to Laravel directories: Twitter DM to @laravelnews, links section on laravel-news.com - Product Hunt launch (launch day) - `reference/configuration.md` full config example (P1 docs gap) - LTD/Pro pricing note on `pricing.astro` (P2) ### Key Files - Landing repo: `landing/` (crontinel/landing) [score=0.825 recalls=5 avg=0.463 source=memory/2026-04-12.md:264-294]

## Promoted From Short-Term Memory (2026-04-17)

<!-- openclaw-memory-promotion:memory:memory/2026-04-12.md:450:470 -->
- - Stripe Price IDs + STRIPE_WEBHOOK_SECRET - App Resend SMTP - Packagist webhook for crontinel/php (needs API token) - @crontinel npm org + mcp-server publish - GitHub OAuth credentials - status.crontinel.com (Gatus on Railway) --- ## Sunday Evening Additions (2026-04-12, ~17:00 BST) ### Tasks Completed This Session - **www.crontinel.com redirect**: Landing uses Cloudflare Workers (NOT Pages) — `@astrojs/cloudflare` adapter with `output: server`. The `_redirects` file approach may not work for Workers. This needs review: either add a redirect in the worker middleware or handle via Cloudflare dashboard redirect rule. The commit was pushed but deployment behavior is TBD. - **Pricing page FAQ gaps**: Added 4 missing FAQ questions to pricing page: free tier limits, self-hosting, data safety, cancel anytime. Previously had 2, now has 6 total. Committed. - **All 20 docs.crontinel.com pages verified 200 OK** via curl. All internal cross-links also confirmed 200. - **MCP guide Pro+ requirement**: Already documented in `mcp/overview.md` and `mcp/tools.md` — confirmed present, no changes needed. - **Dashboard empty-state**: Removed verbatim install instructions (`composer require`, `php artisan`, `.env` blocks) from `app-dashboard.blade.php`. Now shows cleaner "Waiting for first ping..." message with hint about checking logs. Committed from `app/` dir. - Updated LAUNCH_CHECKLIST: checked off items 5, 8, 9, 17, 18 (docs links, MCP Pro+, pricing FAQ, www redirect, dashboard empty-state). ### Browser Session Notes (from earlier in day) [score=0.804 recalls=4 avg=0.454 source=memory/2026-04-12.md:437-457]

### Workflow (2026-04-25)
- **Coding/content/review:** Harun uses cca directly
- **Everything else:** me

## Promoted From Short-Term Memory (2026-04-27)

<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:5:5 -->
- **Fix**: Either manually deploy `feat/landing-redesign` branch via Cloudflare Pages console, or merge to `main` to trigger the Workers deploy. ## Pending ctcc Task - Add Database + Email endpoint types, `/db-ok` health endpoint, and service type icons to the status page system (polishing task, was waiting on response) ## Light Sleep <!-- openclaw:dreaming:light:start --> - Candidate: Landing Redesign — Root Cause Found: **Problem**: Harun asked why ctcc's landing redesign looked wrong on the preview URL. [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:13-20]
<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:7:7 -->
- ## Light Sleep <!-- openclaw:dreaming:light:start --> - Candidate: Landing Redesign — Root Cause Found: **Problem**: Harun asked why ctcc's landing redesign looked wrong on the preview URL. - confidence: 0.00 - evidence: memory/2026-04-19.md:5-5 - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Finding**: The CSS/JS files in `landing/public/` are byte-for-byte correct (identical to handoff). The **preview URL** (`2098ac30-crontinel-landing.crontinel.workers.dev`) was serving the **OLD site** from `main` branch — the `feat/landing-r [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:18-25]
<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:9:9 -->
- - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Finding**: The CSS/JS files in `landing/public/` are byte-for-byte correct (identical to handoff). The **preview URL** (`2098ac30-crontinel-landing.crontinel.workers.dev`) was serving the **OLD site** from `main` branch — the `feat/landing-r - confidence: 0.00 - evidence: memory/2026-04-19.md:7-7 - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Root cause**: Cloudflare Workers workflow (`cloudflare/wrangler-action@v3`) only triggers on `main` push. The preview URL was never rebuilt after the redesign was committed. [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:23-30]
<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:11:11 -->
- - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Root cause**: Cloudflare Workers workflow (`cloudflare/wrangler-action@v3`) only triggers on `main` push. The preview URL was never rebuilt after the redesign was committed. - confidence: 0.00 - evidence: memory/2026-04-19.md:9-9 - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Location of handoff files**: `/tmp/landing-handoff/crontinel-landing/` (also saved as ZIP at `landing/handoffs/Crontinel-Landing-handoff---6e88568f-392c-4906-a6ce-a2072be40547.zip`) [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:28-35]
<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:13:13 -->
- - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Location of handoff files**: `/tmp/landing-handoff/crontinel-landing/` (also saved as ZIP at `landing/handoffs/Crontinel-Landing-handoff---6e88568f-392c-4906-a6ce-a2072be40547.zip`) - confidence: 0.00 - evidence: memory/2026-04-19.md:11-11 - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Fix**: Either manually deploy `feat/landing-redesign` branch via Cloudflare Pages console, or merge to `main` to trigger the Workers deploy. [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:33-40]
<!-- openclaw-memory-promotion:memory:memory/2026-04-19.md:16:16 -->
- - recalls: 0 - status: staged - Candidate: Landing Redesign — Root Cause Found: **Fix**: Either manually deploy `feat/landing-redesign` branch via Cloudflare Pages console, or merge to `main` to trigger the Workers deploy. - confidence: 0.00 - evidence: memory/2026-04-19.md:13-13 - recalls: 0 - status: staged - Candidate: Pending ctcc Task: Add Database + Email endpoint types, `/db-ok` health endpoint, and service type icons to the status page system (polishing task, was waiting on response) [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-19.md:38-45]
<!-- openclaw-memory-promotion:memory:memory/2026-04-20.md:459:461 -->
- - Candidate: Possible Lasting Truths: ## Session Summary — 2026-04-13 Evening ### npm Published - `@crontinel/node@0.1.0` — github.com/crontinel/node - `@crontinel/mcp-server@0.2.0` — github.com/crontinel/mcp-server - Token used: `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` (from Harun) ### Per - confidence: 0.00 - evidence: memory/2026-04-20.md:454-456 [score=0.839 recalls=0 avg=0.620 source=memory/2026-04-20.md:8-10]
