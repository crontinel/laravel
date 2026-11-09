<?php

declare(strict_types=1);

use Crontinel\Data\CronStatus;
use Crontinel\Data\HorizonStatus;
use Crontinel\Data\QueueStatus;
use Crontinel\Laravel\Monitors\CronMonitor;
use Crontinel\Laravel\Monitors\HorizonMonitor;
use Crontinel\Laravel\Monitors\QueueMonitor;
use Crontinel\Services\AlertService;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->artisan('migrate', ['--database' => 'testing']);

    // Explicitly enable horizon — auto-detection (class_exists) doesn't work in tests
    config()->set('crontinel.horizon.enabled', true);

    // Bind mocks
    $this->horizonMock = Mockery::mock(HorizonMonitor::class);
    $this->queueMock = Mockery::mock(QueueMonitor::class);
    $this->cronMock = Mockery::mock(CronMonitor::class);
    $this->alertMock = Mockery::mock(AlertService::class);

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

it('warns on install when the app has no login route', function () {
    config()->set('crontinel.middleware', ['web', 'auth']);

    $this->artisan('crontinel:install')
        ->expectsOutputToContain('no login route')
        ->assertSuccessful();
});

it('does not warn on install when the app has a login route', function () {
    config()->set('crontinel.middleware', ['web', 'auth']);
    Route::get('/login', fn () => 'login')->name('login');
    app('router')->getRoutes()->refreshNameLookups();

    $this->artisan('crontinel:install')
        ->doesntExpectOutputToContain('no login route')
        ->assertSuccessful();
});

it('loads the console schedule before reading it, for web requests', function () {
    app(Kernel::class)->setArtisan(null);
    ConsoleApplication::starting(fn () => app(Schedule::class)->command('inspire')->daily());

    $commands = collect((new CronMonitor)->all())->pluck('command')->join(' ');
    ConsoleApplication::forgetBootstrappers();

    expect($commands)->toContain('inspire');
});
