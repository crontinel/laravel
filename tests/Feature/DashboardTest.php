<?php

declare(strict_types=1);

use Crontinel\Data\HorizonStatus;
use Crontinel\Monitors\CronMonitor;
use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->artisan('migrate', ['--database' => 'testing']);

    // Define a permissive Gate for testing — host apps override this
    Gate::define('crontinel.view', fn () => true);

    $horizonMock = Mockery::mock(HorizonMonitor::class);
    $horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: true,
        supervisors: [['name' => 'supervisor-1', 'status' => 'running', 'processes' => 3, 'queue' => 'default']],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    ));

    $queueMock = Mockery::mock(QueueMonitor::class);
    $queueMock->shouldReceive('all')->andReturn([]);

    $cronMock = Mockery::mock(CronMonitor::class);
    $cronMock->shouldReceive('all')->andReturn([]);

    app()->instance(HorizonMonitor::class, $horizonMock);
    app()->instance(QueueMonitor::class, $queueMock);
    app()->instance(CronMonitor::class, $cronMock);

    // Create a minimal user for authenticated requests
    $this->user = new User;
    $this->user->id = 1;
});

it('serves the dashboard at the configured path', function () {
    $this->actingAs($this->user)->get('/crontinel')->assertStatus(200);
});

it('returns json from the api status endpoint', function () {
    $this->actingAs($this->user)->getJson('/crontinel/api/status')
        ->assertStatus(200)
        ->assertJsonStructure(['horizon', 'queues', 'crons', 'checked_at']);
});
