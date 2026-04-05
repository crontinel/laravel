<?php

declare(strict_types=1);

use CronSentinel\Data\HorizonStatus;

it('is healthy when running with no failed jobs', function () {
    $status = new HorizonStatus(
        running: true,
        supervisors: [['name' => 'supervisor-1', 'status' => 'running']],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeTrue();
});

it('is unhealthy when not running', function () {
    $status = new HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeFalse();
});

it('is unhealthy when paused', function () {
    $status = new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: now(),
    );

    expect($status->isHealthy())->toBeFalse();
});

it('is unhealthy when failed jobs per minute exceeds threshold', function () {
    config()->set('cron-sentinel.horizon.failed_jobs_per_minute_threshold', 5);

    $status = new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 10.0,
        pausedAt: null,
    );

    expect($status->isHealthy())->toBeFalse();
});
