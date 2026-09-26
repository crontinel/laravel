<?php

declare(strict_types=1);

use Crontinel\Listeners\RecordScheduledTaskRun;
use Crontinel\Services\BackgroundRunContext;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config(['crontinel.saas_key' => 'test-key', 'crontinel.cron.background_correlation' => true]);
    $this->priorContext = getenv(BackgroundRunContext::VARIABLE);
});

afterEach(function () {
    putenv($this->priorContext === false ? BackgroundRunContext::VARIABLE : BackgroundRunContext::VARIABLE.'='.$this->priorContext);
    unset($_ENV[BackgroundRunContext::VARIABLE], $_SERVER[BackgroundRunContext::VARIABLE]);
});

it('preserves overlapping background identities when completions arrive in reverse order', function () {
    Http::fake();
    $listener = new RecordScheduledTaskRun;
    $task = app(Schedule::class)->exec('same:command')->runInBackground();
    $contexts = [];
    foreach (range(1, 2) as $i) {
        $listener->handleStarting(new ScheduledTaskStarting($task));
        $task->callBeforeCallbacks(app());
        $contexts[] = getenv(BackgroundRunContext::VARIABLE);
        $listener->handleFinished(new ScheduledTaskFinished($task, 0.001));
        expect(getenv(BackgroundRunContext::VARIABLE))->toBe($this->priorContext);
    }
    foreach (array_reverse($contexts) as $context) {
        putenv(BackgroundRunContext::VARIABLE.'='.$context);
        $copy = app(Schedule::class)->exec('same:command')->runInBackground();
        $copy->exitCode = 7;
        (new RecordScheduledTaskRun)->handleBackgroundFinished(new ScheduledBackgroundTaskFinished($copy));
    }
    $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->data())->all();
    expect($bodies)->toHaveCount(4);
    expect($bodies[0]['request_key'])->not->toBe($bodies[1]['request_key']);
    expect($bodies[0]['request_key'])->toBe($bodies[3]['request_key']);
    expect($bodies[1]['request_key'])->toBe($bodies[2]['request_key']);
    expect($bodies[2]['exit_code'])->toBe(7);
    expect($bodies[0]['started_at'])->toBe($bodies[3]['started_at']);
});

it('does not report starts for an overlap-skipped background task', function () {
    Http::fake();
    $task = app(Schedule::class)->exec('skip:command')->runInBackground()->withoutOverlapping();
    $task->mutex->create($task);
    try {
        $listener = new RecordScheduledTaskRun;
        $listener->handleStarting(new ScheduledTaskStarting($task));
        $task->run(app());
        $listener->handleFinished(new ScheduledTaskFinished($task, 0.001));
        Http::assertNothingSent();
    } finally {
        $task->mutex->forget($task);
    }
});

it('rejects inherited context for another task and falls back for sudo tasks', function () {
    $context = new BackgroundRunContext;
    $task = app(Schedule::class)->exec('one')->runInBackground();
    $run = $context->begin($task);
    try {
        expect($context->recover($task)['key'])->toBe($run['key']);
        expect($context->recover(app(Schedule::class)->exec('two')->runInBackground()))->toBeNull();
        expect($context->begin(app(Schedule::class)->exec('sudo-task')->user('other')))->toBeNull();
        putenv(BackgroundRunContext::VARIABLE.'=not-base64');
        expect($context->recover($task))->toBeNull();
    } finally {
        $context->restore($task);
    }
});

it('correlates a real background shell and schedule finish process without altering the command', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('POSIX shell acceptance fixture');
    }
    $directory = sys_get_temp_dir().'/crontinel-background-'.bin2hex(random_bytes(12));
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views'] as $subdirectory) {
        mkdir($directory.'/'.$subdirectory, 0700, true);
    }
    file_put_contents($directory.'/bootstrap/providers.php', '<?php return [];');
    $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);
    $script = <<<'PHP'
<?php
require AUTOLOAD;
$app = Illuminate\Foundation\Application::configure(basePath: __DIR__)
    ->withExceptions()
    ->withProviders([Crontinel\CrontinelServiceProvider::class])
    ->withSchedule(function ($schedule) {
        $schedule->exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('usleep(300000); exit(7);'))
            ->everyMinute()->runInBackground();
    })->create();
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function () {
    config(['app.key'=>'base64:'.base64_encode(str_repeat('a', 32)), 'app.env'=>'testing',
        'crontinel.saas_key'=>null, 'crontinel.reporting.durable'=>true,
        'crontinel.reporting.spool_path'=>__DIR__.'/spool', 'crontinel.cron.background_correlation'=>true,
        'cache.default'=>'array', 'logging.default'=>'single']);
});

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
config(['crontinel.saas_key'=>'fixture-key']);
$status = $kernel->handle($input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput);
$kernel->terminate($input, $status);
exit($status);
PHP;
    file_put_contents($directory.'/artisan', str_replace('AUTOLOAD', $autoload, $script));
    try {
        $process = new Process([PHP_BINARY, 'artisan', 'schedule:run'], $directory, ['APP_ENV' => 'testing']);
        $process->setTimeout(15)->mustRun();
        $deadline = microtime(true) + 10;
        do {
            $records = array_map(fn ($path) => json_decode(file_get_contents($path), true)['payload'], glob($directory.'/spool/*.json') ?: []);
            $records = array_values(array_filter($records, fn ($record) => str_contains($record['command'], 'usleep(300000)')));
            if (count($records) >= 2) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        expect($records)->toHaveCount(2);
        expect($records[0]['status'])->toBe('running');
        expect($records[1]['status'])->toBe('failed');
        expect($records[1]['request_key'])->toBe($records[0]['request_key']);
        expect($records[1]['started_at'])->toBe($records[0]['started_at']);
        expect($records[1]['command'])->toBe($records[0]['command']);
        expect($records[1]['exit_code'])->toBe(7);
        expect($records[1]['duration_ms'])->toBeGreaterThanOrEqual(300);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('restores parent context after launch failure and correlates the failed report', function () {
    Http::fake();
    putenv(BackgroundRunContext::VARIABLE.'=parent-context');
    $_ENV[BackgroundRunContext::VARIABLE] = 'parent-env';
    $_SERVER[BackgroundRunContext::VARIABLE] = 'parent-server';
    $task = app(Schedule::class)->exec('failure:command')->runInBackground();
    $listener = new RecordScheduledTaskRun;
    $listener->handleStarting(new ScheduledTaskStarting($task));
    $task->callBeforeCallbacks(app());
    $listener->handleFailed(new ScheduledTaskFailed($task, new RuntimeException('launch failed')));
    expect(getenv(BackgroundRunContext::VARIABLE))->toBe('parent-context');
    expect($_ENV[BackgroundRunContext::VARIABLE])->toBe('parent-env');
    expect($_SERVER[BackgroundRunContext::VARIABLE])->toBe('parent-server');
    $reports = Http::recorded()->map(fn ($pair) => $pair[0]->data());
    expect($reports)->toHaveCount(2);
    expect($reports[0]['request_key'])->toBe($reports[1]['request_key']);
    expect($reports[1]['status'])->toBe('failed');
});
