<?php

declare(strict_types=1);

use Crontinel\Data\CronStatus;
use Crontinel\Data\HorizonStatus;
use Crontinel\Data\QueueStatus;
use Crontinel\Services\AlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;


beforeEach(function () {
    Cache::flush();
    config(['crontinel.alerts.channel' => 'slack']);
    config(['crontinel.alerts.slack.webhook_url' => 'https://hooks.slack.com/test']);
    config(['crontinel.horizon.failed_jobs_per_minute_threshold' => 5]);
    config(['crontinel.queues.depth_alert_threshold' => 1000]);
    config(['crontinel.queues.wait_time_alert_seconds' => 300]);
});

// ─── buildHorizonMessage ───────────────────────────────────────────────────

it('fires slack when horizon is not running', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    app(AlertService::class)->evaluateHorizon($status);

    Http::assertSent(fn ($req) => str_contains($req->body(), 'not running'));
});

it('fires slack when horizon is paused', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: Carbon::now()->subMinutes(5),
    );

    app(AlertService::class)->evaluateHorizon($status);

    Http::assertSent(fn ($req) => str_contains($req->body(), 'paused'));
});

it('fires slack when failed jobs per minute exceeds threshold', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 10.0,
        pausedAt: null,
    );

    app(AlertService::class)->evaluateHorizon($status);

    Http::assertSent(fn ($req) => str_contains($req->body(), 'Failed jobs'));
});

it('does not fire when horizon is healthy', function () {
    Http::fake();

    $status = new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.5,
        pausedAt: null,
    );

    app(AlertService::class)->evaluateHorizon($status);

    Http::assertNothingSent();
});

// ─── buildQueueMessage ────────────────────────────────────────────────────

it('fires slack when queue depth exceeds threshold', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 1500,
        failedCount: 0,
        oldestJobAgeSeconds: null,
    );

    app(AlertService::class)->evaluateQueue($status);

    Http::assertSent(fn ($req) =>
        str_contains($req->body(), 'Queue depth') &&
        str_contains($req->body(), '1500')
    );
});

it('fires slack when oldest job exceeds wait threshold', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new QueueStatus(
        connection: 'redis',
        queue: 'emails',
        depth: 50,
        failedCount: 0,
        oldestJobAgeSeconds: 600,
    );

    app(AlertService::class)->evaluateQueue($status);

    Http::assertSent(fn ($req) => str_contains($req->body(), 'Oldest job waiting'));
});

it('does not fire when queue is healthy', function () {
    Http::fake();

    $status = new QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 10,
        failedCount: 0,
        oldestJobAgeSeconds: 5,
    );

    app(AlertService::class)->evaluateQueue($status);

    Http::assertNothingSent();
});

// ─── buildCronMessage ─────────────────────────────────────────────────────

it('fires slack when cron failed', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new CronStatus(
        command: 'send:invoices',
        expression: '0 9 * * *',
        status: 'failed',
        lastRunAt: Carbon::now()->subHour(),
        lastExitCode: 1,
        lastDurationMs: 500,
        nextDueAt: Carbon::now()->addDay(),
    );

    app(AlertService::class)->evaluateCron($status);

    Http::assertSent(fn ($req) =>
        str_contains($req->body(), 'send:invoices') &&
        str_contains($req->body(), 'exited with code')
    );
});

it('fires slack when cron is late', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new CronStatus(
        command: 'process:queue',
        expression: '*/5 * * * *',
        status: 'late',
        lastRunAt: Carbon::now()->subMinutes(20),
        lastExitCode: 0,
        lastDurationMs: 200,
        nextDueAt: Carbon::now()->subMinutes(10),
    );

    app(AlertService::class)->evaluateCron($status);

    Http::assertSent(fn ($req) => str_contains($req->body(), 'schedule'));
});

// ─── deduplication ────────────────────────────────────────────────────────

it('does not re-fire same alert within dedup TTL', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response([], 200)]);

    $status = new HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    $service = app(AlertService::class);
    $service->evaluateHorizon($status);
    $service->evaluateHorizon($status); // second call — should be deduped

    Http::assertSentCount(1);
});

// ─── mail channel ─────────────────────────────────────────────────────────

it('sends mail when channel is mail', function () {
    Mail::fake();
    config(['crontinel.alerts.channel' => 'mail']);
    config(['crontinel.alerts.mail.to' => 'ops@example.com']);

    $status = new HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    app(AlertService::class)->evaluateHorizon($status);

    Mail::assertSent(\Crontinel\Mail\AlertMail::class);
});
