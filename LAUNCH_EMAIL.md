# Crontinel Launch Email Draft

**Status:** DRAFT
**Created:** 2026-04-10
**Send via:** Resend

---

## Subject Line Options (A/B Test)

**A:** Your cron jobs are failing silently. Crontinel is live.

**B:** Crontinel is here. Know when Laravel breaks before your users do.

## Preheader Text

**A:** Free, open-source monitoring for cron, queues, and Horizon. Now available.

**B:** Self-host for free or use the hosted dashboard. One composer require.

---

## Email Body

**From:** Harun
**Reply-to:** harun@crontinel.com

---

Hey,

You signed up for early access to Crontinel. It's live now, and I wanted you to be the first to know.

**What it is:**
Crontinel is an open-source Laravel package that monitors your cron jobs, queues, and Horizon internals. One command to install, zero config to start seeing what's happening.

```
composer require crontinel/laravel
```

**What it watches:**

- Cron exit codes, duration, and late detection
- Queue depth, failed job count, oldest job age
- Horizon per-supervisor status
- Alerts via Slack, email, or webhook when something goes wrong

**Why I built it:**
I kept finding out about failed cron jobs the same way: a user complaint, hours later. Laravel's scheduler doesn't tell you when a job silently stops running or starts taking 10x longer than normal. Crontinel does.

**Who it's for:**
Anyone running Laravel in production with scheduled tasks or queued jobs. Especially if you've ever asked "wait, is that cron even running?"

**What you get:**

- **Self-hosted dashboard**, MIT licensed, completely free
- **Hosted option** at [app.crontinel.com](https://app.crontinel.com) if you don't want to manage it
- **MCP server** for AI coding assistants
- Works with PHP 8.2 through 8.4 and Laravel 11 through 13

---

[**Start for free**](https://crontinel.com)

or

[Self-host from GitHub](https://github.com/crontinel/crontinel) | [Read the docs](https://docs.crontinel.com)

---

If you run into anything or have feedback, just reply to this email. I read every one.

Harun
Building [Crontinel](https://crontinel.com)
