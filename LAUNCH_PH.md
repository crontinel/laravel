# Product Hunt Launch Draft

**Status:** DRAFT
**Created:** 2026-04-10

---

## Tagline

**Your Laravel crons deserve a watchdog.**

---

## Description (PH card)

Open-source monitoring for Laravel cron jobs, queues, and Horizon. Self-hosted dashboard, Slack/email/webhook alerts, and an MCP server so your AI assistant can check production health too. MIT licensed.

---

## First Comment (Maker's Story)

Hey everyone, I'm Harun. I built Crontinel because I kept getting bitten by the same problem: a cron job silently fails at 3am, nobody notices until a customer complains, and then you're digging through logs trying to figure out when it actually broke.

Laravel's scheduler is great at *running* things. But it doesn't tell you when something ran long, exited non-zero, or just... didn't run at all. If you're using Horizon, you get a nice dashboard, but no alerting when a supervisor dies or a queue starts backing up. I wanted one place that watched all of it: crons, queues, Horizon internals.

So I built Crontinel. It's a Laravel package you install with Composer:

```
composer require crontinel/laravel
php artisan crontinel:install
```

It gives you a self-hosted dashboard that tracks:

- Cron exit codes, duration, and late detection (did it actually fire on time?)
- Queue depth per queue, failed job count, oldest job age
- Horizon per-supervisor status

When something goes wrong, it alerts you through Slack, email, or webhooks (with HMAC signing so you can trust the payload).

One thing I'm personally excited about: Crontinel ships with an MCP server. If you use Claude or Cursor, your AI assistant can query your production monitoring data directly. "Is the email queue backed up?" becomes a question you can just ask.

The Laravel package is MIT licensed and fully self-hostable. There's also a framework-agnostic PHP package (`crontinel/php`) if you're not on Laravel. And for teams that don't want to run their own dashboard, there's an early-access SaaS at app.crontinel.com.

**What's next:** Better historical trend views, PagerDuty/OpsGenie integrations, and a scheduling conflict detector that warns you before two crons step on each other.

I'd love feedback from anyone running Laravel in production. What monitoring gaps bug you the most?

---

## Suggested Images/Screenshots (5 total)

1. **Hero/Gallery Image 1: Dashboard overview.** Main Crontinel dashboard with cron jobs listed, last run status (green/red), duration, next expected run. Clean, immediately communicates "monitoring."

2. **Gallery Image 2: Horizon monitoring panel.** Horizon section with supervisor statuses, queue depths as sparklines, failed job count. Differentiates from basic cron monitors.

3. **Gallery Image 3: Alert configuration.** Slack/email/webhook setup screen. Include a sample Slack notification as inset. People want to see the alert before they install.

4. **Gallery Image 4: Terminal install flow.** Clean terminal showing `composer require crontinel/laravel` and `php artisan crontinel:install`. Signals easy setup.

5. **Gallery Image 5: MCP server in action.** Claude or Cursor asking "Are any queues backed up?" and getting a structured response from MCP server. Unique differentiator.

---

## Product Links

- **Website:** https://crontinel.com
- **GitHub:** https://github.com/crontinel/crontinel
- **Docs:** https://docs.crontinel.com
- **SaaS (early access):** https://app.crontinel.com
