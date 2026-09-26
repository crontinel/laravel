# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.0] - 2026-09-26

### Added
- Opt-in background execution correlation with `CRONTINEL_BACKGROUND_CORRELATION=true`. Per-launch environment context preserves UUIDs and start times across the real `schedule:finish` process.
- Wall-clock duration for correlated background completions, overlap-skipped start suppression and scheduler environment restoration. Tasks using `user()` or missing context retain terminal-only reporting.

## [0.6.0] - 2026-09-25

### Added
- Opt-in disk-backed cron reporting with `CRONTINEL_DURABLE_REPORTING=true`. Reports retain their run keys and timestamps across retries without doing HTTP in the scheduled business task.
- Bounded background draining through `crontinel:report` and manual delivery counts through `crontinel:flush-reports`.
- Private, atomic spool records with app/endpoint binding, retry backoff, 24-hour retention, a 12-attempt limit and bounded disk usage. Heartbeats remain live-only.

## [0.5.0] - 2026-09-24

### Added
- Stable request IDs for scheduled executions. Foreground start and terminal reports share an ID; new executions get new IDs.
- One bounded retry for transport errors and HTTP 5xx responses, using the same request payload and ID.
- An optional `requestKey` argument for callers that retransmit a cron report.

### Fixed
- Track starts by task instance rather than command name, avoiding collisions between overlapping tasks.
- Report nonzero process exit codes as failures.
- Report background tasks on `ScheduledBackgroundTaskFinished`, not at process launch. Background reporting is terminal-only; it doesn't detect a missing start or completion.
- Keep local monitoring-storage and logging failures from failing the scheduled business task.

Reports remain synchronous (10-second timeout per attempt, at most two attempts per report). This release doesn't add a persistent client-side spool. Upgrade the hosted server to the atomic keyed-ingest contract before relying on retransmission deduplication.

## [0.4.2] — 2026-08-11

### Security
- **v0.4.0 and v0.4.1 were accidentally published with unrelated internal files bundled into the package tree** (developer tooling scripts, session artifacts, and browser profile data), due to a leaky monorepo checkout being tagged and pushed. This release contains no functional changes — it exists solely to give Composer users a clean version to upgrade to, since Packagist's version-immutability protection prevents overwriting already-published stable tags.
- If you installed `v0.4.0` or `v0.4.1`, upgrade to `^0.4.2` and delete/reinstall your `vendor/crontinel/laravel` directory. Treat any credentials that may have been present in that tree as compromised and rotate them.

## [0.4.1] — 2026-06-25

### Fixed
- Widened the `symfony/process` constraint to support `^8.0`.

## [0.4.0] — 2026-06-17

### Added
- **`crontinel:agent` daemon command**: `php artisan crontinel:agent` connects to the Crontinel SaaS over SSE to receive and execute remote commands, with a systemd/supervisor config generator included.
- **PHP 8.5 support**: `composer.json` now allows PHP 8.5 (required for Laravel 13 compatibility).

### Fixed
- Corrected and normalized the SaaS API URL base and ingest paths so both `https://app.crontinel.com` and a custom `saas_url` (with or without a trailing `/api`) resolve correctly.

## [0.3.0] — 2026-04-08

### Changed
- **Package split**: Core monitoring logic was extracted into a new `crontinel/php` package. `crontinel/laravel` is now a thin Laravel adapter on top of it (requires `crontinel/php: ^0.1`).
- Set `minimum-stability` to `stable` and added `support`/`homepage` fields to `composer.json`.

## [0.2.0] — 2026-04-08

### Added
- **SaaS reporting**: When `CRONTINEL_API_KEY` is set, the package automatically POSTs a status ping to `app.crontinel.com` every minute via a registered scheduled command (`crontinel:report`). Individual cron run events are also pushed after each scheduled task completes or fails.
- **Webhook alert channel**: Set `CRONTINEL_ALERT_CHANNEL=webhook` and `CRONTINEL_WEBHOOK_URL=https://...` to receive structured JSON POST alerts. Supports optional custom headers for authorization. Sends both fire and resolve events.
- **`crontinel:report` command**: Manually trigger a SaaS status ping at any time.
- **`crontinel:prune` command**: Delete old `cron_runs` records beyond the retention window. Accepts `--days=N` to override the config value.

### Fixed
- **Cron `isLate` detection**: Replaced broken `Carbon::now()->next(closure)` with proper `CronExpression::getPreviousRunDate()` to correctly determine when a command was last supposed to run. A cron is now correctly marked late only when the last run predates the previous scheduled time AND the grace period has elapsed.

## [0.1.4] — 2026-04-15

### Fixed
- Added the missing `Gate` facade import in `DashboardController`.
- Corrected test setup for auth gating, Horizon config, and pruning behavior following the 0.1.1 patch.

### Changed
- Removed Laravel 13 from the CI test matrix (`pest-plugin-laravel ^3.0` did not yet support it at the time).

## [0.1.3] — 2026-04-15

### Fixed
- Pint style fixes (unused import, unary operator spacing). No behavior change.

## [0.1.2] — 2026-04-15

### Fixed
- Batched the queue-depth query to remove an N+1 query pattern in `QueueMonitor`.
- Malformed `CRONTINEL_WEBHOOK_HEADERS` JSON is now logged instead of failing silently.

## [0.1.1] — 2026-04-15

### Security
- Webhook alert auth headers are now read from `CRONTINEL_WEBHOOK_HEADERS` (JSON) instead of being hardcoded.

### Fixed
- `AlertMail` now renders via `view()` instead of `text()`, so HTML alert emails render correctly.
- `HorizonMonitor` validates that the configured Redis connection exists before use.
- `RecordScheduledTaskRun` captures the real `startedAt` time via the `ScheduledTaskStarting` event instead of approximating it.

### Changed
- `QueueMonitor` batches the oldest-job-age query (was N+1 per queue).
- `CronMonitor`/`HorizonMonitor` now log a warning on catch-fallback paths instead of failing silently.

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
