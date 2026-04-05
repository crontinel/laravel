<?php

declare(strict_types=1);

use Crontinel\Data\QueueStatus;

it('is healthy when depth and wait time are within thresholds', function () {
    $status = new QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 10,
        failedCount: 0,
        oldestJobAgeSeconds: 30,
    );

    expect($status->isHealthy())->toBeTrue();
});

it('is unhealthy when depth exceeds threshold', function () {
    config()->set('crontinel.queues.depth_alert_threshold', 100);

    $status = new QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 5000,
        failedCount: 0,
        oldestJobAgeSeconds: null,
    );

    expect($status->isHealthy())->toBeFalse();
});

it('is unhealthy when oldest job wait time exceeds threshold', function () {
    config()->set('crontinel.queues.wait_time_alert_seconds', 300);

    $status = new QueueStatus(
        connection: 'redis',
        queue: 'default',
        depth: 1,
        failedCount: 0,
        oldestJobAgeSeconds: 600,
    );

    expect($status->isHealthy())->toBeFalse();
});
