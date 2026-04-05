# Cron Sentinel — [crontinel.com](https://crontinel.com)

Laravel-native Cron & Queue monitoring. Horizon internals, queue depth, dead-letter alerts, and cron health — in one dashboard.

**Generic monitors only check if a job pinged back. Cron Sentinel sees inside Horizon.**

## What it monitors

- **Horizon supervisor health** — running, paused, or missing supervisors
- **Queue depth** — pending jobs per queue with configurable alert thresholds
- **Failed jobs** — per-queue failed job counts + failed-per-minute rate
- **Dead-letter queue** — jobs that exhausted all retries
- **Scheduled commands** — missed, late, or failed cron jobs with duration history

## Installation

```bash
composer require harunrrayhan/cron-sentinel
php artisan cron-sentinel:install
```

Visit `/cron-sentinel` in your browser.

## CLI check

```bash
php artisan cron-sentinel:check
php artisan cron-sentinel:check --format=json
```

## Configuration

After install, edit `config/cron-sentinel.php`:

```php
'horizon' => [
    'enabled' => true,
    'failed_jobs_per_minute_threshold' => 5,
],
'queues' => [
    'watch' => ['default', 'emails', 'heavy'],
    'depth_alert_threshold' => 1000,
],
'alerts' => [
    'channel' => 'slack',
    'slack' => ['webhook_url' => env('CRON_SENTINEL_SLACK_WEBHOOK')],
],
```

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Laravel Horizon (optional, for Horizon monitoring)
