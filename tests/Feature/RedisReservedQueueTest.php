<?php

declare(strict_types=1);

use Crontinel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Redis;

it('counts reserved Redis jobs in direct and discovered queue depth', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default']);
    config()->set('crontinel.queues.watch', ['default']);

    $connection = Mockery::mock();
    $connection->shouldReceive('llen')->once()->with('queues:default')->andReturn(1);
    $connection->shouldReceive('zcard')->once()->with('queues:default:delayed')->andReturn(2);
    $connection->shouldReceive('zcard')->once()->with('queues:default:reserved')->andReturn(3);
    $connection->shouldReceive('lindex')->once()->with('queues:default', -1)->andReturn(null);

    $connection->shouldReceive('pipeline')->twice()->andReturnUsing(function ($callback) {
        static $calls = 0;
        $pipe = Mockery::mock();
        if ($calls++ === 0) {
            $pipe->shouldReceive('llen')->once()->with('queues:default');
            $pipe->shouldReceive('zcard')->once()->with('queues:default:delayed');
            $pipe->shouldReceive('zcard')->once()->with('queues:default:reserved');
            $callback($pipe);

            return [1, 2, 3];
        }

        $pipe->shouldReceive('lindex')->once()->with('queues:default', -1);
        $callback($pipe);

        return [null];
    });
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    $monitor = app(QueueMonitor::class);

    expect($monitor->statusFor('redis', 'default')->depth)->toBe(6)
        ->and($monitor->all()[0]->depth)->toBe(6);
});
