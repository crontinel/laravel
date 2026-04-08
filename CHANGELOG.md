# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] — 2026-04-08

### Added
- **SaaS reporting**: When `CRONTINEL_API_KEY` is set, the package automatically POSTs a status ping to `app.crontinel.com` every minute via a registered scheduled command (`crontinel:report`). Individual cron run events are also pushed after each scheduled task completes or fails.
- **Webhook alert channel**: Set `CRONTINEL_ALERT_CHANNEL=webhook` and `CRONTINEL_WEBHOOK_URL=https://...` to receive structured JSON POST alerts. Supports optional custom headers for authorization. Sends both fire and resolve events.
- **`crontinel:report` command**: Manually trigger a SaaS status ping at any time.
- **`crontinel:prune` command**: Delete old `cron_runs` records beyond the retention window. Accepts `--days=N` to override the config value.

### Fixed
- **Cron `isLate` detection**: Replaced broken `Carbon::now()->next(closure)` with proper `CronExpression::getPreviousRunDate()` to correctly determine when a command was last supposed to run. A cron is now correctly marked late only when the last run predates the previous scheduled time AND the grace period has elapsed.

## [0.1.0] — 2026-04-05

### Added
- Horizon monitor: supervisor status, paused state, failed-jobs-per-minute
- Queue monitor: depth, failed count, oldest job age — Redis and database drivers
- Cron monitor: automatic run recording via `ScheduledTaskFinished` / `ScheduledTaskFailed` events — no wrapping required
- Dashboard at `/crontinel` with dark theme, auto-refreshes every 30 seconds
- `crontinel:check` CLI command — exits 0 (healthy) or 1 (alert), supports `--json` and `--no-alerts` flags
- `crontinel:install` CLI command — publishes config and runs migration in one step
- `crontinel_runs` migration — stores command, exit code, duration, and output per run
- AlertService with Slack webhook and email channels, 5-minute cache-based deduplication
- JSON API endpoint at `/crontinel/api/status`
- Full Pest test suite (18 tests, 39 assertions)
- Support for PHP 8.2 and 8.3, Laravel 11 and 12
