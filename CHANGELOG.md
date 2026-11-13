# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.9.10] - 2026-11-13

### Added

- A Horizon supervisor that is configured for the environment but not running is now reported. Horizon only went unhealthy when the master was stopped or paused, so a dead supervisor was caught only later by queue depth or age. The check waits `crontinel.horizon.supervisor_alert_after_seconds` (default 60, the setting existed but was unused) because Horizon restarts a crashed supervisor within seconds. `crontinel:check` names it and exits non-zero, and the report to the hosted app carries `horizon.missing_supervisors`. It needs a cache store shared between checks (`file` or `redis`), and supervisors are matched by name across all hosts.

## [0.9.9] - 2026-11-13

### Added

- `crontinel.queues.connections` reports queues on connections other than `queue.default`, which were never reported before. List a connection with its queues (`['sqs' => ['emails']]`), or with an empty list to discover them. Their queues are named `connection:queue`, so a `default` queue on two connections no longer collides in alerts and thresholds, and their failed-job counts are read per connection. Without the setting, nothing changes.

## [0.9.8] - 2026-11-13

### Fixed

- On a Redis Cluster, queue discovery read the keys of only one node, so a busy queue that lived on another node was never reported unless Horizon or `crontinel.queues.watch` named it. Discovery now scans every master (phpredis). If scanning fails for any reason, the queues Horizon is configured to work are still reported instead of falling back to `default` alone.

## [0.9.7] - 2026-11-13

### Fixed

- A paused Horizon reported the time of each check as when it was paused, so every alert said "paused just now" and the hosted app saw a pause that started a minute ago, however long it had lasted. Horizon does not record when it was paused, so the first check that saw it paused now stands in for the start, and it clears once Horizon runs again. This needs a cache store shared between checks, such as `file` or `redis`.

## [0.9.6] - 2026-11-12

### Fixed

- On a Redis queue connection, a queue with no jobs has no Redis key, so it stopped being reported the moment it drained. Its depth of 0 and its failed-job count disappeared from the status, and the hosted app could not tell the queue had recovered. The queues Horizon's supervisors are configured to work are now always reported, so an empty one shows depth 0 and its failed count.
- Queue discovery on Redis used `KEYS`, which walks the whole keyspace in one blocking call and runs every minute. It now uses `SCAN`. Setting `crontinel.queues.watch` still bypasses discovery.

## [0.9.5] - 2026-11-12

### Fixed

- A count or timestamp recorded with `Outcome::metric()` or `Outcome::timestamp()` inside a command run by the scheduler (`Schedule::command()`) now reaches that run's receipt under default settings. The scheduler runs the command as a separate process, so the value was dropped and the run showed an unknown outcome. Closures were not affected. Tasks that use `runInBackground()` still need `CRONTINEL_BACKGROUND_CORRELATION=true`.

## [0.9.4] - 2026-11-09

### Fixed

- `crontinel:report` no longer reads Horizon when Horizon monitoring is off, which logged two warnings on every report in apps without Horizon.
- Crontinel's own scheduled commands no longer appear in `crontinel:check`, the dashboard or the hosted status report.

## [0.9.3] - 2026-11-09

### Security

- The dashboard now checks the `crontinel.view` gate, as the status API already did, so guests are refused even if `auth` is removed from `crontinel.middleware`.
- The dashboard and status API show the command name without its arguments, which can hold secrets.

### Fixed

- `crontinel:check`, the `/crontinel` dashboard and the hosted status report crashed on installs from Packagist. `crontinel/php` declares monitor classes with the same names, and its versions were loaded instead. The Laravel monitors now live in `Crontinel\\Laravel\\Monitors`.
- The `/crontinel` dashboard listed no scheduled commands on Laravel 11+, because web requests don't load `routes/console.php` or `withSchedule()`.
- `crontinel:install` warns when the app has no `login` route, because the default `auth` middleware makes the dashboard return a 500 there.

## [0.9.2] - 2026-11-03

### Fixed

- `crontinel:schedule` now turns on hosted missed-run alerts. It sends the schedule as named by the owner, with each task's timezone (the task's own, else `app.schedule_timezone`, else `app.timezone`). Before, the hosted app stored the schedule as inventory only.
- Crontinel's own `crontinel:report` task is no longer recorded or reported as one of your runs.

## [0.9.1] - 2026-11-02

### Security

- Background-task outcomes and output are buffered under `storage/framework/crontinel-outcomes` with owner-only (0600) files, instead of the shared system temp directory. Symlinked paths are refused.

## [0.9.0] - 2026-11-02

### Added
- `Outcome::output()` sets the text sent as a scheduled run's output. It goes on the terminal receipt only, cut to 10,000 characters. A failed run keeps the exception message and adds this text after it.
- Opt-in `CRONTINEL_CAPTURE_OUTPUT=true` (`crontinel.cron.capture_output`) sends the last 10,000 characters of a task's output file from `sendOutputTo()`, `appendOutputTo()` or `storeOutput()`, including background tasks. Off by default because output may hold sensitive data. `Outcome::output()` wins when both are set.
- Documented the AI agent ceiling metric names `tokens_used`, `cost_usd` and `tool_calls` for `Outcome::metric()`. The hosted AI quality check that grades output is opt-in in the hosted app.

### Fixed
- Durable reporting stores non-ASCII output unescaped, so a full 10,000-character output fits the 64 KiB spool record.

## [0.8.0] - 2026-10-09

### Added
- Record a business result on a scheduled run with `Outcome::metric()` and `Outcome::timestamp()`. Exit code 0 still means the process finished. A zero count is sent when the job records it. A run that records nothing omits `outcomes`.
- Send `job_name` from `Schedule::name()`, plus `expression` and a single `environment`, so a renamed command or a `Schedule::call` closure can keep a stable name.
- `php artisan crontinel:schedule` names those tasks in Crontinel and can set the `processed_records` minimum that means the run counted.

## [0.7.4] - 2026-09-29

### Fixed
- Wait for the hosted ingest API's `Retry-After` delay before replaying a throttled durable cron report.
- Discard a monthly allowance rejection with a redacted warning when its reset is beyond the durable spool's 24-hour retention.

## [0.7.3] - 2026-09-28

### Fixed
- Read configured Redis Cluster queues individually when PhpRedis cannot pipeline them. This restores batch depth and oldest pending age in the SDK monitor.

## [0.7.2] - 2026-09-28

### Fixed
- Read live Horizon master and supervisor records from their sorted sets and hashes using Horizon's Redis connection, including paused state and recent failed jobs.
- Measure Redis queue wait age from the oldest pending job and support Laravel's `createdAt` payload field.

## [0.7.1] - 2026-09-28

### Fixed
- Read database queue depth and oldest-job age from the queue's configured connection and table.
- Count reserved Redis jobs in queue depth, matching Laravel's queue size.
- Read Horizon supervisor hashes when Redis keys use a prefix.
- Wait up to two seconds for a contended durable report spool lock before dropping a report. This affects only opt-in durable reporting under storage contention.

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
