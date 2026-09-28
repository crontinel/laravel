<?php

declare(strict_types=1);

use Crontinel\Monitors\HorizonMonitor;
use Illuminate\Support\Facades\Redis;

it('reads supervisor hashes returned by a prefixed Redis connection', function () {
    config()->set('database.redis.options.prefix', 'fixture-');
    config()->set('database.redis.horizon', ['host' => '127.0.0.1']);
    config()->set('crontinel.horizon.connection', 'horizon');

    $redis = Mockery::mock();
    $redis->shouldReceive('keys')->once()->with('horizon:supervisors:*')
        ->andReturn(['fixture-horizon:supervisors:one']);
    $redis->shouldReceive('hmget')->once()->with('horizon:supervisors:one', ['name', 'status', 'processes', 'queue'])
        ->andReturn(['supervisor-1', 'running', '2', 'default']);
    $redis->shouldReceive('smembers')->once()->with('horizon:masters')->andReturn(['master-1']);
    $redis->shouldReceive('hmget')->once()->with('master-1', ['status'])->andReturn(['running']);
    $redis->shouldReceive('get')->once()->with('horizon:failed_jobs_per_minute')->andReturn(null);
    Redis::shouldReceive('connection')->with('horizon')->andReturn($redis);

    $status = app(HorizonMonitor::class)->status();

    expect($status->running)->toBeTrue()
        ->and($status->supervisors)->toBe([[
            'name' => 'supervisor-1',
            'status' => 'running',
            'processes' => 2,
            'queue' => 'default',
        ]]);
});
