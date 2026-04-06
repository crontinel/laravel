<?php

declare(strict_types=1);

use Crontinel\Listeners\RecordScheduledTaskRun;
use Crontinel\Models\CronRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('records a successful scheduled task run', function () {
    $task = makeScheduledEvent('php artisan inspire');

    $listener = new RecordScheduledTaskRun;
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.124));

    $run = CronRun::latest('ran_at')->first();

    expect($run)->not->toBeNull()
        ->and($run->command)->toBe('php artisan inspire')
        ->and($run->exit_code)->toBe(0)
        ->and($run->duration_ms)->toBe(124);
});

it('records a failed scheduled task run with exit code 1', function () {
    $task = makeScheduledEvent('php artisan send-invoices');
    $exception = new RuntimeException('Something went wrong');

    $listener = new RecordScheduledTaskRun;
    $listener->handleFailed(new ScheduledTaskFailed($task, $exception));

    $run = CronRun::latest('ran_at')->first();

    expect($run)->not->toBeNull()
        ->and($run->command)->toBe('php artisan send-invoices')
        ->and($run->exit_code)->toBe(1)
        ->and($run->output)->toBe('Something went wrong');
});

it('does not record runs when cron monitoring is disabled', function () {
    config()->set('crontinel.cron.enabled', false);

    $task = makeScheduledEvent('php artisan inspire');
    $listener = new RecordScheduledTaskRun;
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.05));

    expect(CronRun::count())->toBe(0);
});

it('prunes old cron runs beyond retain_days', function () {
    config()->set('crontinel.cron.retain_days', 7);

    CronRun::create([
        'command' => 'php artisan old-task',
        'ran_at' => now()->subDays(10),
        'exit_code' => 0,
        'duration_ms' => 100,
    ]);

    expect(CronRun::count())->toBe(1);

    $task = makeScheduledEvent('php artisan inspire');
    $listener = new RecordScheduledTaskRun;
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.05));

    expect(CronRun::count())->toBe(1)
        ->and(CronRun::first()->command)->toBe('php artisan inspire');
});

// Use Mockery to create a proper Illuminate\Console\Scheduling\Event mock
function makeScheduledEvent(string $command): Event
{
    $mock = Mockery::mock(Event::class);
    $mock->command = $command;
    $mock->description = null;

    return $mock;
}
