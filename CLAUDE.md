# Crontinel — CLAUDE.md

> **Source of truth:** See `AI_CONTEXT.md` for the canonical context used by all AI agents.
> This file contains additional Claude Code-specific notes below.

## What this product is

**Laravel Cron & Queue Sentinel** — a Laravel-native monitoring tool that goes deeper than generic cron monitors (Cronitor, Better Stack, Forge Heartbeats). It monitors:

- Laravel Horizon supervisor health (not just ping-backs)
- Queue depth per queue/connection
- Failed job rate and dead-letter queue alerts
- Cron job execution — missed, late, or failed
- Per-job duration trends and anomaly detection

**Domain:** crontinel.com ✅ (registered)
**Target market:** Laravel developers running Horizon + background jobs in production
**Pricing anchor:** Cronitor charges $2/monitor/month with 100K+ devs — we target the same market with deeper Laravel-native insight
**Moat:** Generic monitors only check if a job pinged back. We see Horizon internals. Forge covers the surface — we go deeper.

## Score summary (from research)
- Score: 86.0 / 100
- Build estimate: 2–3 weeks for MVP
- Verdict: Build first

## Stack (MVP — keep it simple)
- Laravel (PHP) — the product IS for Laravel devs, so dogfood it
- SQLite for MVP persistence (upgrade to MySQL/Postgres when needed)
- Livewire for dashboard UI (no JS build step, fast iteration)
- Laravel package structure for distribution via Composer
- Pest for tests

## Product structure
```
cron-sentinel/
├── src/           # Package source (ServiceProvider, Models, Jobs, etc.)
├── database/      # Migrations
├── resources/     # Livewire views
├── routes/        # Web routes for dashboard
├── tests/         # Pest tests
├── config/        # crontinel.php config file
└── workbench/     # Local test app for dev (Laravel Workbench)
```

## Dev conventions
- Follow PSR-12
- Use strict types
- No unnecessary abstractions — solve the actual problem first
- Write Pest tests for core monitoring logic
- Keep the package installable in 2 commands: `composer require` + `php artisan crontinel:install`

## Key files to know
- `src/CronSentinelServiceProvider.php` — package entry point
- `src/Monitors/HorizonMonitor.php` — Horizon health checks
- `src/Monitors/QueueMonitor.php` — queue depth + failed jobs
- `src/Monitors/CronMonitor.php` — scheduled command tracking
- `config/crontinel.php` — user-facing config

## Research context
Full research in: /Users/ray/.openclaw/workspace/projects/product-opportunity/research/
Final rankings: /Users/ray/.openclaw/workspace/projects/product-opportunity/scoring/final-ranking-2026-04-05.md
