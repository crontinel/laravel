# Twitter/X Launch Thread Draft

**Status:** DRAFT
**Created:** 2026-04-10

---

**1/5**

Your Laravel cron jobs are failing in production and nobody knows.

Queue workers die silently. Horizon supervisors go down at 2am.

You find out when a customer emails you.

We built Crontinel to fix this.

---

**2/5**

Crontinel is an open-source Laravel package that monitors:

- Cron exit codes, duration, and late detection
- Queue depth per queue and failed job counts
- Horizon per-supervisor status and oldest job age

Laravel-native. No external services required.

It also ships an MCP server so your AI tools can query your monitoring directly.

---

**3/5**

Two commands to get started:

composer require crontinel/laravel
php artisan crontinel:install

That gives you a local dashboard tracking every scheduled task, every queue, and every Horizon process.

PHP 8.2+ / Laravel 11, 12, 13.

---

**4/5**

The package is MIT licensed and always will be.

Self-hosted dashboard is free. Run it on your own infra, own your data.

If you want alerts, multi-app views, and a managed dashboard, there's an optional SaaS at app.crontinel.com (early access now).

---

**5/5**

Give it a try and let us know what breaks.

GitHub: https://github.com/crontinel/crontinel
Docs: https://docs.crontinel.com
Site: https://crontinel.com

Stars, issues, and PRs all welcome.
