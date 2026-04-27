---
title: I built a cron job monitor for Laravel in 5 minutes with Crontinel
published: false
description: Crontinel is an open-source Laravel package that monitors your cron jobs, queues, and Horizon internals. Two commands to install, a built-in dashboard, and alerts when things go wrong.
tags: laravel, php, opensource, webdev
cover_image: https://crontinel.com/og-image.png
---

If you run scheduled commands in Laravel, you've probably had that moment where something silently stopped working and nobody noticed for hours. Maybe days.

A report that should go out every morning just... didn't. A queue worker died and jobs piled up. Horizon paused itself after a deploy and nobody restarted it. You find out because a customer emails you, not because your monitoring told you.

I got tired of that. So I built Crontinel.

## What's already out there (and why it wasn't enough)

There are existing tools. Here's the thing though:

**Heartbeat monitors (Cronitor, Healthchecks.io)** tell you a job ran. They don't tell you that your Horizon supervisor is paused, or that your `emails` queue has 4,000 jobs backed up while your `default` queue is fine. They monitor pings, not application internals.

**Custom health endpoints** work, but you end up maintaining a `/health` route that checks Redis, checks the database, maybe pokes Horizon. It's bespoke code you wrote at 2am that nobody wants to touch. Every new queue or scheduled task means updating the health check manually.

**The Horizon dashboard** is great for looking at things in real time. But it won't Slack you at 3am when a supervisor crashes. It's a dashboard, not a monitor.

I wanted something that understood Laravel's internals, installed in under 5 minutes, and could actually alert me when things broke.

## Crontinel: what it does

Crontinel is an MIT-licensed Laravel package. It monitors three things:

1. **Horizon internals** per supervisor (not just "is Horizon running?"), paused state, and failed jobs per minute
2. **Queue depth** per queue, failed count, and oldest job age (supports both Redis and database drivers)
3. **Cron jobs** with exit codes, duration tracking, and late detection

The cron tracking part is automatic. Crontinel listens to Laravel's `ScheduledTaskFinished` and `ScheduledTaskFailed` events. You don't wrap your commands or change your schedule. It just works.

## Installing it

Two commands:

```bash
composer require crontinel/laravel
php artisan crontinel:install
```

The install command publishes the config file and runs the migration for the `crontinel_runs` table. Open `/crontinel` in your browser and you've got a dashboard.

That's it. No API keys, no external service, no account signup. It runs entirely inside your app.

## Setting up alerts

Edit `config/crontinel.php` or just set a few env vars:

```env
CRONTINEL_ALERT_CHANNEL=slack
CRONTINEL_SLACK_WEBHOOK=https://hooks.slack.com/services/T00/B00/xxxx
```

Crontinel fires alerts when:

- Horizon is stopped or a supervisor goes down
- Failed jobs per minute crosses your threshold
- Queue depth or wait time gets too high
- A scheduled command exits with a non-zero code
- A cron job is late (missed its expected run window)

Alerts auto-resolve. When the issue clears, you get a "resolved" notification. Same alert won't fire more than once every 5 minutes, so your Slack channel stays readable.

You can also use email or a webhook endpoint with HMAC verification if you want to pipe alerts into your own system.

## The CLI check

There's a health check command you can use in CI, deploy scripts, or external monitoring:

```bash
php artisan crontinel:check
```

Exits `0` when healthy, `1` when any alert is active. Add `--format=json` for machine-readable output:

```bash
php artisan crontinel:check --format=json
```

I use this in a post-deploy step to verify everything came back up correctly after a release.

## The MCP server angle

This is the part I'm most excited about. Crontinel ships a companion MCP server (`@crontinel/mcp-server`) that lets AI coding assistants query your monitoring data directly.

If you use Claude Code or Cursor, you can ask things like "are any of my queues backed up?" or "did the nightly report cron run successfully?" and get real answers from your actual monitoring data. No switching to a browser dashboard.

It's a small thing, but when you're deep in a debugging session and you want to check if a queue is healthy without leaving your editor, it saves context switches.

## Configuration that matters

The defaults are sensible, but here are the knobs you'll probably want to adjust:

```php
// config/crontinel.php

'horizon' => [
    'supervisor_alert_after_seconds' => 60,    // how long before alerting on a down supervisor
    'failed_jobs_per_minute_threshold' => 5,   // spike detection
],

'queues' => [
    'watch' => [],                   // empty = auto-discover all queues
    'depth_alert_threshold' => 1000, // jobs backed up
    'wait_time_alert_seconds' => 300, // oldest job too old
],

'cron' => [
    'late_alert_after_seconds' => 120, // cron didn't run on time
    'retain_days' => 30,               // how long to keep run history
],
```

Not using Horizon? Set `horizon.enabled` to `false`. Queue and cron monitoring work independently.

## Optional: the hosted SaaS

The OSS package is fully self-contained. But if you have multiple apps or want longer history and team access, there's a hosted version at [app.crontinel.com](https://app.crontinel.com) in early access. Connect it by setting one env var:

```env
CRONTINEL_API_KEY=your-api-key
```

The package then reports to the SaaS in addition to running locally. Free tier includes one app with 7 days of history.

## Try it out

```bash
composer require crontinel/laravel
php artisan crontinel:install
# visit /crontinel
```

The repo is at [github.com/crontinel/crontinel](https://github.com/crontinel/crontinel). Stars and feedback are both welcome.

If you've been burned by silent cron failures or queue backups that nobody noticed, give it a shot. If something doesn't work or you want a feature, open an issue. I read all of them.

Full docs are at [docs.crontinel.com](https://docs.crontinel.com).
