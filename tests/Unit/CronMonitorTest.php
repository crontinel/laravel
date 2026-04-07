<?php

declare(strict_types=1);

use Crontinel\Data\CronStatus;
use Illuminate\Support\Carbon;


// ─── CronStatus::isHealthy ────────────────────────────────────────────────

it('is healthy when status is ok', function () {
    $status = new CronStatus(
        command: 'send:invoices',
        expression: '0 9 * * *',
        status: 'ok',
        lastRunAt: Carbon::now()->subHour(),
        lastExitCode: 0,
        lastDurationMs: 200,
        nextDueAt: Carbon::now()->addDay(),
    );

    expect($status->isHealthy())->toBeTrue();
});

it('is not healthy when status is failed', function () {
    $status = new CronStatus(
        command: 'send:invoices',
        expression: '0 9 * * *',
        status: 'failed',
        lastRunAt: Carbon::now()->subHour(),
        lastExitCode: 1,
        lastDurationMs: 50,
        nextDueAt: Carbon::now()->addDay(),
    );

    expect($status->isHealthy())->toBeFalse();
});

it('is not healthy when status is late', function () {
    $status = new CronStatus(
        command: 'process:queue',
        expression: '*/5 * * * *',
        status: 'late',
        lastRunAt: Carbon::now()->subMinutes(20),
        lastExitCode: 0,
        lastDurationMs: 100,
        nextDueAt: Carbon::now()->subMinutes(10),
    );

    expect($status->isHealthy())->toBeFalse();
});

it('is not healthy when status is never_run', function () {
    $status = new CronStatus(
        command: 'prune:old-data',
        expression: '0 2 * * *',
        status: 'never_run',
        lastRunAt: null,
        lastExitCode: null,
        lastDurationMs: null,
        nextDueAt: Carbon::now()->addDay(),
    );

    expect($status->isHealthy())->toBeFalse();
});

// ─── QueueStatus::isHealthy ───────────────────────────────────────────────

it('queue is healthy within thresholds', function () {
    config(['crontinel.queues.depth_alert_threshold' => 1000]);
    config(['crontinel.queues.wait_time_alert_seconds' => 300]);

    $status = new \Crontinel\Data\QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 50,
        failedCount: 0,
        oldestJobAgeSeconds: 10,
    );

    expect($status->isHealthy())->toBeTrue();
});

it('queue is unhealthy when depth exceeds threshold', function () {
    config(['crontinel.queues.depth_alert_threshold' => 500]);

    $status = new \Crontinel\Data\QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 600,
        failedCount: 0,
        oldestJobAgeSeconds: null,
    );

    expect($status->isHealthy())->toBeFalse();
});

it('queue is unhealthy when oldest job age exceeds wait threshold', function () {
    config(['crontinel.queues.depth_alert_threshold' => 1000]);
    config(['crontinel.queues.wait_time_alert_seconds' => 120]);

    $status = new \Crontinel\Data\QueueStatus(
        connection: 'redis',
        queue: 'emails',
        depth: 5,
        failedCount: 0,
        oldestJobAgeSeconds: 300,
    );

    expect($status->isHealthy())->toBeFalse();
});

it('queue is healthy when oldest job age is null', function () {
    config(['crontinel.queues.depth_alert_threshold' => 1000]);

    $status = new \Crontinel\Data\QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 0,
        failedCount: 0,
        oldestJobAgeSeconds: null,
    );

    expect($status->isHealthy())->toBeTrue();
});

// ─── HorizonStatus::isHealthy ─────────────────────────────────────────────

it('horizon is healthy when running and not paused', function () {
    config(['crontinel.horizon.failed_jobs_per_minute_threshold' => 5]);

    $status = new \Crontinel\Data\HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.5,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeTrue();
});

it('horizon is unhealthy when not running', function () {
    $status = new \Crontinel\Data\HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeFalse();
});

it('horizon is unhealthy when paused', function () {
    $status = new \Crontinel\Data\HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: Carbon::now()->subMinutes(3),
    );

    expect($status->isHealthy())->toBeFalse();
});

it('horizon is unhealthy when failed rate exceeds threshold', function () {
    config(['crontinel.horizon.failed_jobs_per_minute_threshold' => 5]);

    $status = new \Crontinel\Data\HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 8.0,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeFalse();
});
