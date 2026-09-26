# Crontinel Laravel

[![Latest Version](https://img.shields.io/packagist/v/crontinel/laravel.svg)](https://packagist.org/packages/crontinel/laravel)
[![CI](https://github.com/crontinel/laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/crontinel/laravel/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/crontinel/laravel.svg)](https://packagist.org/packages/crontinel/laravel)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-blue)](https://packagist.org/packages/crontinel/laravel)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red)](https://packagist.org/packages/crontinel/laravel)
[![License](https://img.shields.io/github/license/crontinel/laravel.svg)](LICENSE)

**Background job and cron monitoring for Laravel.** Monitors Horizon internals, queue depth, and cron health — things generic monitors can't see.

> Cronitor tells you a job ran. Crontinel tells you your Horizon supervisor is paused.

---

> ⚠️ **Security notice (2026-08-11):** v0.4.0 and v0.4.1 were accidentally published with unrelated internal files bundled in, including credential scripts. Upgrade to `^0.4.2` immediately and rotate any credentials from a `vendor/crontinel/laravel` tree installed before this date. See [CHANGELOG.md](CHANGELOG.md#042).

---

## What it monitors

| Monitor | What it sees |
|---|---|
| **Horizon** | Supervisor status per supervisor (not just "Horizon is running"), paused state, failed jobs per minute |
| **Queues** | Depth per queue, failed count, oldest job age — Redis and database drivers |
| **Cron jobs** | Every scheduled command run: exit code, duration, late detection |

---

## Requirements

- PHP 8.2, 8.3, or 8.4
- Laravel 11, 12, or 13

---

## Installation

```bash
composer require crontinel/laravel
php artisan crontinel:install
```

That's it. Visit `/crontinel` in your browser.

`crontinel:install` publishes the config file and runs the migration for the `crontinel_runs` table.

**Cron run tracking is automatic** — Crontinel listens to Laravel's `ScheduledTaskFinished` and `ScheduledTaskFailed` events. No wrapping or modification of your scheduled commands needed.

---

## CLI health check

```bash
# Table output (human-readable)
php artisan crontinel:check

# JSON output (for CI/CD integration)
php artisan crontinel:check --format=json

# Check without firing alerts
php artisan crontinel:check --no-alerts
```

Exits with code `0` when all monitors are healthy, `1` if any alert is active. Use this in CI or monitoring pipelines.

---

## Configuration

After install, edit `config/crontinel.php`:

```php
return [
    // Dashboard URL path (default: /crontinel)
    'path' => env('CRONTINEL_PATH', 'crontinel'),

    // Dashboard middleware
    'middleware' => ['web', 'auth'],

    // Connect to Crontinel SaaS for multi-app hosted dashboards (optional)
    'saas_key' => env('CRONTINEL_API_KEY'),
    'saas_url' => env('CRONTINEL_API_URL', 'https://app.crontinel.com'),

    'horizon' => [
        'enabled'                          => true,
        'supervisor_alert_after_seconds'   => 60,
        'failed_jobs_per_minute_threshold' => 5,
        'connection'                       => 'horizon', // Redis connection name for Horizon
    ],

    'queues' => [
        'enabled'                 => true,
        'watch'                   => [],    // empty = auto-discover
        'depth_alert_threshold'   => 1000,
        'wait_time_alert_seconds' => 300,
    ],

    'cron' => [
        'enabled'                  => true,
        'late_alert_after_seconds' => 120,
        'retain_days'              => 30,
    ],

    'alerts' => [
        'channel' => env('CRONTINEL_ALERT_CHANNEL'), // 'slack' or 'mail'
        'mail'  => ['to' => env('CRONTINEL_ALERT_EMAIL')],
        'slack' => ['webhook_url' => env('CRONTINEL_SLACK_WEBHOOK')],
    ],
];
```

### Environment variables

```env
# Dashboard path (optional)
CRONTINEL_PATH=crontinel

# Alerts — set channel to 'slack' or 'mail'
CRONTINEL_ALERT_CHANNEL=slack
CRONTINEL_SLACK_WEBHOOK=https://hooks.slack.com/...
CRONTINEL_ALERT_EMAIL=ops@yourcompany.com

# SaaS reporting (optional)
CRONTINEL_API_KEY=your-api-key
```

---

## Alerts

Crontinel fires alerts when:

- Horizon is stopped or paused
- A supervisor process goes down
- Failed jobs per minute exceeds threshold
- Queue depth or oldest job age exceeds threshold
- A scheduled command exits with non-zero code
- A scheduled command is late (missed its expected run window)

Alerts **auto-resolve** and send a "resolved" notification when the issue clears.

**Alert deduplication:** The same alert won't fire more than once per 5 minutes for the same issue.

---

## Not using Horizon?

Set `horizon.enabled = false` in config. Queue and cron monitoring work independently of Horizon.

---

## Connecting to Crontinel SaaS

The OSS package works standalone. To get multi-app hosted dashboards, longer history, and team access, visit [crontinel.com](https://crontinel.com) to join the early access list.

### Durable cron reporting

To keep cron evidence through temporary network failures without making scheduled jobs wait for HTTP, opt in:

```env
CRONTINEL_DURABLE_REPORTING=true
# Optional: a private persistent directory, outside the public web root
CRONTINEL_REPORT_SPOOL_PATH=/var/lib/my-app/crontinel-spool
```

Rebuild Laravel's configuration cache after changing these values. The default directory is `storage/app/crontinel-spool`. Give the scheduler user write access. Use a separate directory per application on a local filesystem with working `flock` and atomic rename. Ephemeral container storage doesn't survive replacement; mount persistent storage if that matters for your deployment. All producers and the drainer must use the same path and OS user.

Cron start/completion reports are written locally before delivery. The existing every-minute `crontinel:report` schedule drains them in its background process. You still need Laravel's scheduler running. No queue worker is required. Manually drain one batch with:

```bash
php artisan crontinel:flush-reports
```

The command prints sent, retried, discarded and pending counts, plus busy/error flags. A batch attempts at most 20 reports in a 20-second delivery window, with a maximum three-second HTTP timeout per attempt. Retry delays double from one minute to one hour. Each record gets at most 12 attempts and is retained for at most 24 hours; cleanup happens during a drain. Network errors, HTTP 5xx, 401, 403, 408 and 429 are retried. Other non-2xx responses are discarded with a redacted warning. Redirects aren't followed.

The spool holds at most 1,000 records, each at most 64 KiB (roughly 64 MiB total, plus temporary files). Full storage, oversized payloads or an enqueue lock unavailable for 250 ms cause a report to be dropped with a warning. Monitoring storage/logging failures don't fail the scheduled business command. Delivery is bounded best-effort with at-least-once replay, not a guarantee against disk loss. Original request keys and execution timestamps survive retries; the hosted keyed-ingest contract deduplicates replays.

Files are private to their owner but contain the reported command/output in plaintext. No API key is stored. Pending records are bound to the original normalized endpoint and a key fingerprint: changing the app key or host won't send old evidence to the new destination. Drain before rotating credentials where possible. Otherwise, records remain until the original configuration is restored or they expire. Turning durable mode off stops draining; retained files need a later drain or manual removal.

Heartbeats aren't spooled or replayed. Durable delivery can delay cron alerts by the drain/retry interval. With durable mode off, cron reporting keeps its synchronous behavior: at most two 10-second attempts.

### Background execution correlation

Set `CRONTINEL_BACKGROUND_CORRELATION=true` to send start/completion pairs for `runInBackground()` tasks. Rebuild the configuration cache and restart long-running schedulers after changing it. Each launch carries its own UUID and original start time through the child process environment to Laravel's `schedule:finish`. Overlapping executions keep separate identities even when they finish out of order. The SDK restores the scheduler's previous environment after launch or launch failure. Task commands and Laravel's mutex names aren't rewritten.

Starts are recorded after the overlap lock is acquired. Skipped background tasks don't emit a start. If the process dies before `schedule:finish`, the accepted start remains unfinished for the hosted runtime monitor to evaluate. Duration is wall-clock time from the start hook to the completion event, including reporting and completion-hook overhead.

Tasks using `user()` retain terminal-only reporting because `sudo` may remove the environment. Missing or invalid inherited context also falls back to terminal-only reporting. Keep the application configuration and schedule definition stable while a task is running. Don't clear `CRONTINEL_SCHEDULE_CONTEXT` in the surrounding scheduler shell. Pair this setting with durable reporting to avoid HTTP waits at launch. Both features are opt-in.

---

## License

MIT — free forever. See [LICENSE](LICENSE).

Built by [Harun R Rayhan](https://github.com/HarunRRayhan) · [crontinel.com](https://crontinel.com)
