<?php

declare(strict_types=1);

use Crontinel\Commands\PruneCommand;
use Crontinel\Listeners\RecordScheduledTaskRun;
use Crontinel\Models\CronRun;
use Crontinel\Services\SaasReporter;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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

it('prunes old cron runs beyond retain_days via PruneCommand', function () {
    config()->set('crontinel.cron.retain_days', 7);

    CronRun::create([
        'command' => 'php artisan old-task',
        'ran_at' => now()->subDays(10),
        'exit_code' => 0,
        'duration_ms' => 100,
    ]);

    expect(CronRun::count())->toBe(1);

    // Prune is now handled exclusively by PruneCommand, not inline
    $this->artisan('crontinel:prune')->assertExitCode(0);

    expect(CronRun::count())->toBe(0);
});

// Use Mockery to create a proper Illuminate\Console\Scheduling\Event mock

it('keeps identities separate for overlapping instances of the same command', function () {
    config(['crontinel.saas_key' => 'test-key']);
    Http::fake();
    $listener = app(RecordScheduledTaskRun::class);
    $first = makeScheduledEvent('same:command');
    $second = makeScheduledEvent('same:command');
    $listener->handleStarting(new ScheduledTaskStarting($first));
    $listener->handleStarting(new ScheduledTaskStarting($second));
    $listener->handleFinished(new ScheduledTaskFinished($second, 0.1));
    $listener->handleFinished(new ScheduledTaskFinished($first, 0.2));
    $listener->handleFinished(new ScheduledTaskFinished($first, 0.2));
    $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->data())->all();
    expect($bodies)->toHaveCount(4)
        ->and($bodies[0]['request_key'])->not->toBe($bodies[1]['request_key'])
        ->and($bodies[0]['request_key'])->toBe($bodies[3]['request_key'])
        ->and($bodies[1]['request_key'])->toBe($bodies[2]['request_key'])
        ->and($bodies[0]['started_at'])->toBe($bodies[3]['started_at']);
    $listener->handleStarting(new ScheduledTaskStarting($first));
    expect(Http::recorded()->last()[0]['request_key'])->not->toBe($bodies[0]['request_key']);
});

it('uses the actual nonzero exit code from a finished command', function () {
    config(['crontinel.saas_key' => 'test-key']);
    Http::fake();
    $task = makeScheduledEvent('fails:command');
    $task->exitCode = 7;
    app(RecordScheduledTaskRun::class)->handleFinished(new ScheduledTaskFinished($task, 0.1));
    Http::assertSent(fn ($request) => $request['status'] === 'failed' && $request['exit_code'] === 7);
});

it('does not report background launch as completed', function () {
    config(['crontinel.saas_key' => 'test-key']);
    Http::fake();
    $task = makeScheduledEvent('background:command');
    $task->runInBackground = true;
    $listener = app(RecordScheduledTaskRun::class);
    $listener->handleStarting(new ScheduledTaskStarting($task));
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.1));
    Http::assertNothingSent();
    $task->exitCode = 0;
    $listener->handleBackgroundFinished(new ScheduledBackgroundTaskFinished($task));
    Http::assertSent(fn ($request) => $request['status'] === 'completed' && isset($request['request_key']));
});

it('reuses request identity across a transient HTTP retry', function () {
    config(['crontinel.saas_key' => 'test-key']);
    Http::fake(['*' => Http::sequence()->push([], 503)->push([], 200)]);
    app(SaasReporter::class)->reportCronRun('retry:test', 0, 1, null, '2026-09-24T10:00:00Z', '2026-09-24T10:00:01Z');
    $requests = Http::recorded();
    expect($requests)->toHaveCount(2)
        ->and($requests[0][0]->data())->toBe($requests[1][0]->data());
});

it('keeps reporting if the local monitoring table is unavailable', function () {
    config(['crontinel.saas_key' => 'test-key']);
    Http::fake();
    Schema::drop('crontinel_runs');
    app(RecordScheduledTaskRun::class)->handleFinished(new ScheduledTaskFinished(makeScheduledEvent('test'), 0.1));
    Http::assertSentCount(1);
});

function makeScheduledEvent(string $command): Event
{
    $mock = Mockery::mock(Event::class);
    $mock->command = $command;
    $mock->description = null;

    return $mock;
}
