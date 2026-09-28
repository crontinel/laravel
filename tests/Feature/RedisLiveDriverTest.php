<?php

declare(strict_types=1);

use Crontinel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Redis;

it('matches Laravel Redis size while pending jobs become reserved', function () {
    if (getenv('CRONTINEL_LIVE_REDIS') !== '1') {
        test()->markTestSkipped('Requires a disposable Redis server and CRONTINEL_LIVE_REDIS=1.');
    }

    $prefix = 'crontinel-sdk-'.bin2hex(random_bytes(6)).'-';
    config()->set('database.redis.client', 'phpredis');
    config()->set('database.redis.options.prefix', $prefix);
    config()->set('database.redis.default', [
        'host' => getenv('CRONTINEL_REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CRONTINEL_REDIS_PORT') ?: 6379),
        'database' => 0,
    ]);
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', [
        'driver' => 'redis', 'connection' => 'default', 'queue' => 'default', 'retry_after' => 90,
    ]);
    config()->set('crontinel.queues.watch', ['matrix']);

    $redis = Redis::connection('default');
    $queue = app('queue')->connection('redis');
    $monitor = app(QueueMonitor::class);
    $old = json_encode(['job' => 'matrix-job', 'data' => [], 'attempts' => 0, 'createdAt' => time() - 120]);
    $new = json_encode(['job' => 'matrix-job', 'data' => [], 'attempts' => 0, 'createdAt' => time() - 5]);

    try {
        $queue->pushRaw($old, 'matrix');
        $queue->pushRaw($new, 'matrix');
        $queue->later(300, 'matrix-job', '', 'matrix');

        $pending = $monitor->statusFor('redis', 'matrix');
        $discovered = $monitor->all()[0];
        expect($pending->depth)->toBe($queue->size('matrix'))
            ->and($pending->depth)->toBe(3)
            ->and($pending->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120)
            ->and($discovered->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120);

        $queue->pop('matrix');
        $reserved = $monitor->statusFor('redis', 'matrix');
        expect($reserved->depth)->toBe($queue->size('matrix'))
            ->and($reserved->depth)->toBe(3)
            ->and($reserved->oldestJobAgeSeconds)->toBeLessThan(30);

        $queue->pop('matrix');
        $onlyDelayedAndReserved = $monitor->statusFor('redis', 'matrix');
        expect($onlyDelayedAndReserved->depth)->toBe($queue->size('matrix'))
            ->and($onlyDelayedAndReserved->depth)->toBe(3)
            ->and($onlyDelayedAndReserved->oldestJobAgeSeconds)->toBeNull()
            ->and($monitor->all()[0]->oldestJobAgeSeconds)->toBeNull();
    } finally {
        foreach (['', ':notify', ':delayed', ':reserved'] as $suffix) {
            $redis->del('queues:matrix'.$suffix);
        }
    }
});
