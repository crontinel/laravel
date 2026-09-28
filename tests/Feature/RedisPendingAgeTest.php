<?php

declare(strict_types=1);

use Crontinel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Redis;

it('uses the oldest pending Laravel Redis payload for direct and batch age', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default']);
    config()->set('crontinel.queues.watch', ['default']);

    $oldest = json_encode(['createdAt' => time() - 120, 'attempts' => 0]);
    $connection = Mockery::mock();
    $connection->shouldReceive('llen')->once()->with('queues:default')->andReturn(2);
    $connection->shouldReceive('zcard')->once()->with('queues:default:delayed')->andReturn(1);
    $connection->shouldReceive('zcard')->once()->with('queues:default:reserved')->andReturn(1);
    $connection->shouldReceive('lindex')->once()->with('queues:default', 0)->andReturn($oldest);
    $connection->shouldReceive('pipeline')->twice()->andReturnUsing(function ($callback) use ($oldest) {
        static $calls = 0;
        $pipe = Mockery::mock();
        if ($calls++ === 0) {
            $pipe->shouldReceive('llen')->once()->with('queues:default');
            $pipe->shouldReceive('zcard')->once()->with('queues:default:delayed');
            $pipe->shouldReceive('zcard')->once()->with('queues:default:reserved');
            $callback($pipe);

            return [2, 1, 1];
        }

        $pipe->shouldReceive('lindex')->once()->with('queues:default', 0);
        $callback($pipe);

        return [$oldest];
    });
    Redis::shouldReceive('connection')->with('default')->andReturn($connection);

    $monitor = app(QueueMonitor::class);
    $direct = $monitor->statusFor('redis', 'default');
    $batch = $monitor->all()[0];

    expect($direct->depth)->toBe(4)
        ->and($batch->depth)->toBe(4)
        ->and($direct->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120)
        ->and($batch->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(120);
});
