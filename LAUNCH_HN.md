# Show HN Post Draft

**Status:** DRAFT
**Created:** 2026-04-10

---

**Title:** Show HN: Crontinel - Laravel cron job and queue monitoring with a Composer package

**Body:**

I built Crontinel because I kept getting bitten by silent cron failures. A scheduled job would stop running, or Horizon would quietly lose a supervisor, and nobody would notice until a user reported stale data. Laravel's built-in monitoring tells you what's scheduled but not whether it actually ran, how long it took, or if it's drifting late.

Crontinel is an open-source Laravel package that monitors three things:

1. **Cron health** - tracks exit codes, execution duration, and detects late runs
2. **Queue depth** - per-queue job counts, failed job counts, oldest job age
3. **Horizon internals** - supervisor status per supervisor, not just "Horizon is running"

It hooks into Laravel's scheduler events automatically, so you don't need to modify your existing scheduled commands. Install and you're monitoring:

```
composer require crontinel/laravel
php artisan crontinel:install
```

The package ships with a local dashboard, Slack/email/webhook alerts (webhooks include HMAC verification), and a CLI command (`php artisan crontinel:check`) you can plug into CI/CD or external health checks. Supports PHP 8.2 through 8.4 and Laravel 11 through 13. MIT licensed.

One thing I'm excited about: there's a companion MCP server package (`crontinel/mcp-server`) that lets AI assistants like Claude or Cursor query your monitoring data directly. You can ask "are any queues backing up?" or "which cron jobs failed this week?" and get real answers from your actual system state.

There's also a framework-agnostic PHP core (`crontinel/php`) if you're not on Laravel.

The OSS package is the full product. There is an optional hosted SaaS (app.crontinel.com, early access) that adds team access, longer retention, and multi-app dashboards, but you don't need it. The local package does everything on its own.

GitHub: https://github.com/crontinel/crontinel
Docs: https://docs.crontinel.com
Site: https://crontinel.com

I'd genuinely appreciate feedback on the package, the API surface, the docs, anything. Happy to answer questions about the implementation.
