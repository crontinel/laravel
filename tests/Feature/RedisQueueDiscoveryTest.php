<?php

declare(strict_types=1);

use Crontinel\Laravel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Redis;

function redisQueueFixture(): void
{
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default']);
    config()->set('crontinel.queues.watch', []);
    config()->set('database.redis.client', 'phpredis');
    config()->set('database.redis.options.prefix', '');
}

function discoveredQueues(): array
{
    $method = new ReflectionMethod(QueueMonitor::class, 'resolveQueues');

    return $method->invoke(app(QueueMonitor::class), 'redis', []);
}

it('finds queue keys with SCAN across pages and never calls KEYS', function () {
    redisQueueFixture();
    $connection = Mockery::mock();
    $connection->shouldReceive('keys')->never();
    $connection->shouldReceive('scan')->once()->with(null, Mockery::on(fn ($o) => $o['match'] === 'queues:*'))
        ->andReturn([17, ['queues:emails', 'queues:emails:reserved']]);
    // phpredis returns false, not an empty page, when the last page has nothing.
    $connection->shouldReceive('scan')->once()->with(17, Mockery::any())->andReturn([0, ['queues:default:notify', 'queues:billing:delayed']]);
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toEqualCanonicalizing(['emails', 'billing']);
});

it('stops when SCAN returns false for the last page', function () {
    redisQueueFixture();
    $connection = Mockery::mock();
    $connection->shouldReceive('scan')->once()->with(null, Mockery::any())->andReturn([9, ['queues:emails']]);
    $connection->shouldReceive('scan')->once()->with(9, Mockery::any())->andReturn(false);
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toBe(['emails']);
});

it('also reports the queues Horizon is configured to work, so an empty one does not disappear', function () {
    redisQueueFixture();
    config()->set('horizon.defaults', [
        'supervisor-default' => ['connection' => 'redis', 'queue' => ['default']],
        'supervisor-emails' => ['connection' => 'redis', 'queue' => ['emails', 'notifications']],
        'supervisor-other-connection' => ['connection' => 'sqs', 'queue' => ['elsewhere']],
    ]);
    // The environment overrides the defaults supervisor by supervisor.
    config()->set('horizon.environments.testing', ['supervisor-emails' => ['queue' => ['emails', 'invoices']]]);
    $connection = Mockery::mock();
    $connection->shouldReceive('scan')->andReturn(false); // no queue has a job, so no key exists
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toEqualCanonicalizing(['default', 'emails', 'invoices']);
});

it('starts phpredis at a null cursor and prefixes the SCAN pattern, which phpredis does not do itself', function () {
    redisQueueFixture();
    config()->set('database.redis.options.prefix', 'app-');
    $connection = Mockery::mock();
    // phpredis returns the keys with the prefix, and the package strips it again.
    $connection->shouldReceive('scan')->once()->with(null, Mockery::on(fn ($o) => $o['match'] === 'app-queues:*'))->andReturn([0, ['app-queues:emails']]);
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toBe(['emails']);
});

it('starts Predis at cursor 0 and prefixes its SCAN pattern too', function () {
    redisQueueFixture();
    config()->set('database.redis.client', 'predis');
    config()->set('database.redis.options.prefix', 'app-');
    $connection = Mockery::mock();
    $connection->shouldReceive('scan')->once()->with(0, Mockery::on(fn ($o) => $o['match'] === 'app-queues:*'))->andReturn(['0', ['app-queues:emails']]);
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toBe(['emails']);
});

it('falls back to the default queue when nothing is found and Horizon is not configured', function () {
    redisQueueFixture();
    $connection = Mockery::mock();
    $connection->shouldReceive('scan')->andReturn(false);
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    expect(discoveredQueues())->toBe(['default']);
});

it('discovers queues on a real Redis with a key prefix, and reports a configured empty queue at depth 0', function () {
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
    redisQueueFixture();
    config()->set('horizon.defaults', ['supervisor-billing' => ['connection' => 'redis', 'queue' => ['billing']]]);
    $queue = app('queue')->connection('redis');
    $redis = Redis::connection('default');
    $job = json_encode(['job' => 'discovery-job', 'data' => [], 'attempts' => 0, 'createdAt' => time() - 30]);

    try {
        $queue->pushRaw($job, 'emails');

        // emails has a job (a key); billing is configured for Horizon but empty (no key).
        expect(discoveredQueues())->toEqualCanonicalizing(['emails', 'billing']);
        $byName = collect(app(QueueMonitor::class)->all())->keyBy('queue');
        expect($byName['emails']->depth)->toBe(1)
            ->and($byName['billing']->depth)->toBe(0)
            ->and($byName['billing']->oldestJobAgeSeconds)->toBeNull();
    } finally {
        foreach (['', ':notify', ':delayed', ':reserved'] as $suffix) {
            $redis->del('queues:emails'.$suffix);
        }
    }
});
