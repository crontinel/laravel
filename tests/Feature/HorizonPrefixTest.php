<?php

declare(strict_types=1);

use Crontinel\Monitors\HorizonMonitor;
use Illuminate\Support\Facades\Redis;

it('reads active Horizon master and supervisor records through the Horizon connection', function () {
    config()->set('database.redis.horizon', ['host' => '127.0.0.1', 'options' => ['prefix' => 'app_horizon:']]);
    config()->set('crontinel.horizon.connection', 'horizon');

    $redis = Mockery::mock();
    $redis->shouldReceive('zrevrangebyscore')->once()->with('masters', '+inf', Mockery::type('int'))
        ->andReturn(['host-master']);
    $redis->shouldReceive('hmget')->once()->with('master:host-master', ['name', 'status'])
        ->andReturn(['name' => 'host-master', 'status' => 'running']);
    $redis->shouldReceive('zrevrangebyscore')->once()->with('supervisors', '+inf', Mockery::type('int'))
        ->andReturn(['host-master:supervisor-1']);
    $redis->shouldReceive('hmget')->once()->with('supervisor:host-master:supervisor-1', ['name', 'status', 'processes', 'options'])
        ->andReturn([
            'name' => 'host-master:supervisor-1',
            'status' => 'running',
            'processes' => '{"redis:default":2}',
            'options' => '{"queue":["default"]}',
        ]);
    $redis->shouldReceive('zcount')->once()->with('failed_jobs', Mockery::type('float'), Mockery::type('float'))
        ->andReturn(1);
    Redis::shouldReceive('connection')->with('horizon')->andReturn($redis);

    $status = app(HorizonMonitor::class)->status();

    expect($status->running)->toBeTrue()
        ->and($status->failedJobsPerMinute)->toBe(1.0)
        ->and($status->supervisors)->toBe([[
            'name' => 'host-master:supervisor-1',
            'status' => 'running',
            'processes' => 2,
            'queue' => 'default',
        ]]);
});

it('does not treat stale Horizon names as active', function () {
    config()->set('database.redis.horizon', ['host' => '127.0.0.1']);
    $redis = Mockery::mock();
    $redis->shouldReceive('zrevrangebyscore')->with('masters', '+inf', Mockery::type('int'))->andReturn([]);
    $redis->shouldReceive('zrevrangebyscore')->with('supervisors', '+inf', Mockery::type('int'))->andReturn([]);
    $redis->shouldReceive('zcount')->andReturn(0);
    Redis::shouldReceive('connection')->with('horizon')->andReturn($redis);

    $status = app(HorizonMonitor::class)->status();

    expect($status->running)->toBeFalse()->and($status->supervisors)->toBe([]);
});

it('marks a paused Horizon master and supervisor as unhealthy', function () {
    config()->set('database.redis.horizon', ['host' => '127.0.0.1']);
    $redis = Mockery::mock();
    $redis->shouldReceive('zrevrangebyscore')->with('masters', '+inf', Mockery::type('int'))
        ->andReturn(['host-master']);
    $redis->shouldReceive('hmget')->with('master:host-master', ['name', 'status'])
        ->andReturn(['host-master', 'paused']);
    $redis->shouldReceive('zrevrangebyscore')->with('supervisors', '+inf', Mockery::type('int'))
        ->andReturn(['host-master:supervisor-1']);
    $redis->shouldReceive('hmget')->with('supervisor:host-master:supervisor-1', ['name', 'status', 'processes', 'options'])
        ->andReturn(['host-master:supervisor-1', 'paused', '{"redis:default":1}', '{"queue":["default"]}']);
    $redis->shouldReceive('zcount')->andReturn(0);
    Redis::shouldReceive('connection')->with('horizon')->andReturn($redis);

    $status = app(HorizonMonitor::class)->status();

    expect($status->running)->toBeFalse()
        ->and($status->pausedAt)->not->toBeNull()
        ->and($status->supervisors[0]['status'])->toBe('paused')
        ->and($status->isHealthy())->toBeFalse();
});

it('marks a paused supervisor unhealthy while its master stays running', function () {
    config()->set('database.redis.horizon', ['host' => '127.0.0.1']);
    $redis = Mockery::mock();
    $redis->shouldReceive('zrevrangebyscore')->with('masters', '+inf', Mockery::type('int'))
        ->andReturn(['host-master']);
    $redis->shouldReceive('hmget')->with('master:host-master', ['name', 'status'])
        ->andReturn(['host-master', 'running']);
    $redis->shouldReceive('zrevrangebyscore')->with('supervisors', '+inf', Mockery::type('int'))
        ->andReturn(['host-master:supervisor-1']);
    $redis->shouldReceive('hmget')->with('supervisor:host-master:supervisor-1', ['name', 'status', 'processes', 'options'])
        ->andReturn(['host-master:supervisor-1', 'paused', '{"redis:default":1}', '{"queue":["default"]}']);
    $redis->shouldReceive('zcount')->andReturn(0);
    Redis::shouldReceive('connection')->with('horizon')->andReturn($redis);

    $status = app(HorizonMonitor::class)->status();

    expect($status->running)->toBeTrue()
        ->and($status->pausedAt)->not->toBeNull()
        ->and($status->isHealthy())->toBeFalse();
});
