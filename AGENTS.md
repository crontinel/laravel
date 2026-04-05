# Crontinel — Agent Instructions

> Full context is in AI_CONTEXT.md. Read that file first before making any changes.

## For any AI agent working on this repo

1. Read `AI_CONTEXT.md` — stack, conventions, key files, build order
2. Read `PRD.md` — full product spec with acceptance criteria per milestone
3. Read `ARCHITECTURE.md` — all confirmed decisions (don't re-litigate these)
4. Check which milestone is active before writing any code
5. Run `php artisan test` or `./vendor/bin/pest` to verify tests pass after changes

## Quick reference

- Product: Crontinel (crontinel.com) — Laravel Cron & Queue monitoring
- Namespace: `Crontinel\` | Commands: `crontinel:*` | DB table: `crontinel_runs`
- Primary dev channel: Claude Code via OpenClaw (Telegram → OpenClaw → claude CLI)
- Repo: github.com/HarunRRayhan/crontinel | Local: ~/Work/crontinel
