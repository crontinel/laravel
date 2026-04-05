# Crontinel — AI Agent Context

> This is the single source of truth for all AI coding assistants working on this repo.
> CLAUDE.md, .cursorrules, AGENTS.md, and .github/copilot-instructions.md all reference this file.
> **Update this file only.** All agents will follow it.

---

## What this repo is

**Crontinel** (`crontinel.com`) — Laravel-native Cron & Queue monitoring.
- OSS MIT package (`harunrrayhan/crontinel` on Packagist) + hosted SaaS at `app.crontinel.com`
- Monitors Laravel Horizon internals, queue depth, failed jobs, and cron job execution
- The moat: generic monitors check if a job pinged back. Crontinel reads Horizon internals.

**Repos:**
- This repo: `~/Work/crontinel` → `github.com/HarunRRayhan/crontinel`
- Symlinked at: `~/.openclaw/workspace/projects/crontinel`

**Full specs:** See `PRD.md` (product requirements) and `ARCHITECTURE.md` (all decisions).

---

## Stack

| Layer | Tech |
|---|---|
| OSS package | PHP 8.2+, Laravel 11/12, Blade, Pest |
| SaaS app | Laravel 12, Livewire 3, Alpine.js, PostgreSQL, Redis |
| Landing page | Astro + Tailwind, Cloudflare Pages (hybrid: static core + SSR SEO pages) |
| Docs | Astro Starlight, Cloudflare Pages |
| Billing | Laravel Cashier + Stripe |
| Auth | Laravel Breeze + GitHub OAuth (Socialite) |
| Email | Resend (start) → AWS SES (when production access approved) |
| Infra | AWS EC2 + RDS PostgreSQL (if credits) or Hetzner + Neon (fallback) |
| Status page | Gatus (Docker on VPS) |

---

## PHP conventions

- Strict types on every file: `declare(strict_types=1);`
- PSR-12 formatting (enforced via Laravel Pint)
- Namespace: `Crontinel\` (not `CronSentinel\`)
- Artisan commands: `crontinel:install`, `crontinel:check`
- Config key: `config('crontinel.*')`
- DB table: `crontinel_runs`
- Tests: Pest only (no PHPUnit directly)

---

## Key files

| File | Purpose |
|---|---|
| `src/CrontinelServiceProvider.php` | Package entry point |
| `src/Monitors/HorizonMonitor.php` | Horizon health via Redis keys |
| `src/Monitors/QueueMonitor.php` | Queue depth + failed jobs |
| `src/Monitors/CronMonitor.php` | Scheduled command tracking |
| `src/Data/HorizonStatus.php` | Value object + isHealthy() |
| `src/Data/QueueStatus.php` | Value object + isHealthy() |
| `src/Data/CronStatus.php` | Value object + status enum |
| `src/Commands/InstallCommand.php` | crontinel:install |
| `src/Commands/CheckCommand.php` | crontinel:check |
| `src/Http/Controllers/DashboardController.php` | Web dashboard + API status |
| `config/crontinel.php` | All thresholds + alert config |
| `database/migrations/*crontinel_runs*` | Cron run history table |
| `resources/views/dashboard.blade.php` | Dark dashboard UI |
| `routes/web.php` | Dashboard + API routes |

---

## Development rules

1. **No unnecessary abstractions** — solve the actual problem, not a hypothetical one
2. **No extra error handling** for impossible cases — trust framework guarantees
3. **No speculative features** — only build what's in the PRD
4. **No comments** unless logic is non-obvious
5. **No breaking changes** to the public API without updating PRD first
6. **Tests required** for all monitor logic and alert evaluation
7. **Never break the OSS package** — SaaS features go in the app, not the package

---

## Build order (current milestone)

Check `PRD.md` section 14 for the active milestone and its acceptance criteria.
Do not start a milestone until the previous one's checklist is complete.

**Milestones:**
1. Landing page (crontinel.com) — 1 day
2. OSS Package MVP — 2–3 weeks
3. SaaS Auth + Teams — 1 week
4. SaaS Agent Integration — 1 week
5. Billing (Stripe) — 1 week
6. Polish + MCP server + Launch — 1 week

---

## Agent-specific notes

- **Primary AI:** Claude Code via OpenClaw (Telegram → OpenClaw → `claude` CLI)
- **No approval needed** for: reading files, writing code, running tests, git commits/pushes
- **Always confirm before:** deleting files, dropping DB tables, changing public API contracts
- When in doubt: read `PRD.md` — the answer is probably there
