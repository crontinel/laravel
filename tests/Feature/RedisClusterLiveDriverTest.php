<?php

declare(strict_types=1);

use Crontinel\Laravel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Redis;

it('falls back to direct reads when a Redis client cannot pipeline queues', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default']);
    config()->set('crontinel.queues.watch', ['{alpha}', '{beta}']);

    $old = json_encode(['createdAt' => time() - 120]);
    $connection = Mockery::mock();
    $connection->shouldReceive('pipeline')->twice()->andThrow(new RuntimeException('Pipeline unsupported'));
    foreach (['{alpha}', '{beta}'] as $name) {
        $connection->shouldReceive('llen')->once()->with('queues:'.$name)->andReturn(1);
        $connection->shouldReceive('zcard')->once()->with('queues:'.$name.':delayed')->andReturn(1);
        $connection->shouldReceive('zcard')->once()->with('queues:'.$name.':reserved')->andReturn(1);
        $connection->shouldReceive('lindex')->once()->with('queues:'.$name, 0)->andReturn($old);
    }
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    $statuses = app(QueueMonitor::class)->all();
    expect($statuses)->toHaveCount(2)
        ->and($statuses[0]->depth)->toBe(3)
        ->and($statuses[1]->depth)->toBe(3)
        ->and($statuses[0]->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120)
        ->and($statuses[1]->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120);
});

it('matches Laravel queue depth and pending age through PhpRedis Cluster', function () {
    if (getenv('CRONTINEL_LIVE_REDIS_CLUSTER') !== '1') {
        test()->markTestSkipped('Requires a disposable Redis Cluster and CRONTINEL_LIVE_REDIS_CLUSTER=1.');
    }

    $queueName = '{crontinel-cluster-'.bin2hex(random_bytes(6)).'}';
    $otherQueue = '{crontinel-cluster-'.bin2hex(random_bytes(6)).'}';
    config()->set('database.redis.client', 'phpredis');
    config()->set('database.redis.options.prefix', 'crontinel-sdk:');
    config()->set('database.redis.default', null);
    config()->set('database.redis.clusters.default', [[
        'host' => getenv('CRONTINEL_REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CRONTINEL_REDIS_PORT') ?: 6379),
    ]]);
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', [
        'driver' => 'redis', 'connection' => 'default', 'queue' => $queueName, 'retry_after' => 90,
    ]);
    config()->set('crontinel.queues.watch', [$queueName, $otherQueue]);

    $redis = Redis::connection('default');
    $queue = app('queue')->connection('redis');
    $monitor = app(QueueMonitor::class);
    $old = json_encode(['job' => 'cluster-job', 'data' => [], 'attempts' => 0, 'createdAt' => time() - 120]);

    try {
        $queue->pushRaw($old, $queueName);
        $queue->later(300, 'cluster-job', '', $queueName);
        $queue->pushRaw($old, $otherQueue);

        $direct = $monitor->statusFor('redis', $queueName);
        $discovered = $monitor->all();
        expect($direct->depth)->toBe($queue->size($queueName))
            ->and($direct->depth)->toBe(2)
            ->and($direct->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120)
            ->and($discovered[0]->depth)->toBe(2)
            ->and($discovered[0]->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120)
            ->and($discovered[1]->depth)->toBe($queue->size($otherQueue))
            ->and($discovered[1]->depth)->toBe(1)
            ->and($discovered[1]->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120);
    } finally {
        foreach ([$queueName, $otherQueue] as $name) {
            foreach (['', ':notify', ':delayed', ':reserved'] as $suffix) {
                $redis->del('queues:'.$name.$suffix);
            }
        }
    }
});

it('discovers queues on a Redis Cluster, and still reports Horizon queues when keys cannot be scanned', function () {
    if (getenv('CRONTINEL_LIVE_REDIS_CLUSTER') !== '1') {
        test()->markTestSkipped('Requires a disposable Redis Cluster and CRONTINEL_LIVE_REDIS_CLUSTER=1.');
    }

    config()->set('database.redis.client', 'phpredis');
    config()->set('database.redis.options.prefix', 'crontinel-sdk:');
    config()->set('database.redis.default', null);
    config()->set('database.redis.clusters.default', [[
        'host' => getenv('CRONTINEL_REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CRONTINEL_REDIS_PORT') ?: 6379),
    ]]);
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('crontinel.queues.watch', []);
    config()->set('horizon.defaults', ['supervisor-1' => ['connection' => 'redis', 'queue' => ['{idle-horizon-queue}']]]);

    // Several hash tags, so on a multi-node cluster the keys land on different masters.
    $busy = array_map(fn ($n) => '{crontinel-busy-'.$n.'}', range(1, 8));
    $queue = app('queue')->connection('redis');
    foreach ($busy as $name) {
        $queue->pushRaw(json_encode(['job' => 'x', 'data' => [], 'attempts' => 0, 'createdAt' => time()]), $name);
    }

    try {
        $names = array_map(fn ($status) => $status->queue, app(QueueMonitor::class)->all());

        // Every busy queue is found whichever node holds it, and the configured Horizon queue is reported although it has no key.
        expect($names)->toContain('{idle-horizon-queue}')->and($names)->toContain(...$busy);
    } finally {
        foreach ($busy as $name) {
            Redis::connection('default')->del('queues:'.$name);
        }
    }
});
