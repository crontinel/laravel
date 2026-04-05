<?php

declare(strict_types=1);

use Crontinel\Monitors\CronMonitor;
use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Crontinel\Data\HorizonStatus;
use Crontinel\Data\QueueStatus;
use Crontinel\Data\CronStatus;
use Crontinel\Services\AlertService;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->artisan('migrate', ['--database' => 'testing']);

    // Bind mocks
    $this->horizonMock = Mockery::mock(HorizonMonitor::class);
    $this->queueMock   = Mockery::mock(QueueMonitor::class);
    $this->cronMock    = Mockery::mock(CronMonitor::class);
    $this->alertMock   = Mockery::mock(AlertService::class);

    app()->instance(HorizonMonitor::class, $this->horizonMock);
    app()->instance(QueueMonitor::class, $this->queueMock);
    app()->instance(CronMonitor::class, $this->cronMock);
    app()->instance(AlertService::class, $this->alertMock);
});

it('exits 0 when all monitors are healthy', function () {
    $this->horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: true,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    ));

    $this->queueMock->shouldReceive('all')->andReturn([
        new QueueStatus('database', 'default', depth: 5, failedCount: 0, oldestJobAgeSeconds: 10),
    ]);

    $this->cronMock->shouldReceive('all')->andReturn([]);

    $this->alertMock->shouldReceive('evaluateHorizon')->once();
    $this->alertMock->shouldReceive('evaluateQueue')->once();

    $this->artisan('crontinel:check')->assertExitCode(0);
});

it('exits 1 when horizon is stopped', function () {
    $this->horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: false,
        supervisors: [],
        failedJobsPerMinute: 0.0,
        pausedAt: null,
    ));

    $this->queueMock->shouldReceive('all')->andReturn([]);
    $this->cronMock->shouldReceive('all')->andReturn([]);

    $this->alertMock->shouldReceive('evaluateHorizon')->once();

    $this->artisan('crontinel:check')->assertExitCode(1);
});

it('exits 1 when a cron job has failed', function () {
    $this->horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: true, supervisors: [], failedJobsPerMinute: 0.0, pausedAt: null,
    ));

    $this->queueMock->shouldReceive('all')->andReturn([]);

    $this->cronMock->shouldReceive('all')->andReturn([
        new CronStatus(
            command: 'php artisan send-invoices',
            expression: '0 0 1 * *',
            status: 'failed',
            lastRunAt: now()->subHour(),
            lastExitCode: 1,
            lastDurationMs: 500,
            nextDueAt: now()->addMonth(),
        ),
    ]);

    $this->alertMock->shouldReceive('evaluateHorizon')->once();
    $this->alertMock->shouldReceive('evaluateCron')->once();

    $this->artisan('crontinel:check')->assertExitCode(1);
});

it('outputs json when --format=json flag is passed', function () {
    $this->horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: true, supervisors: [], failedJobsPerMinute: 0.0, pausedAt: null,
    ));
    $this->queueMock->shouldReceive('all')->andReturn([]);
    $this->cronMock->shouldReceive('all')->andReturn([]);
    $this->alertMock->shouldReceive('evaluateHorizon')->once();

    $this->artisan('crontinel:check', ['--format' => 'json'])
        ->assertExitCode(0);
});

it('skips alerts when --no-alerts flag is passed', function () {
    $this->horizonMock->shouldReceive('status')->andReturn(new HorizonStatus(
        running: true, supervisors: [], failedJobsPerMinute: 0.0, pausedAt: null,
    ));
    $this->queueMock->shouldReceive('all')->andReturn([]);
    $this->cronMock->shouldReceive('all')->andReturn([]);

    // AlertService should not be called at all
    $this->alertMock->shouldNotReceive('evaluateHorizon');
    $this->alertMock->shouldNotReceive('evaluateQueue');
    $this->alertMock->shouldNotReceive('evaluateCron');

    $this->artisan('crontinel:check', ['--no-alerts' => true])->assertExitCode(0);
});
