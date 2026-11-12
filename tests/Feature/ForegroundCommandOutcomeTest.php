<?php

declare(strict_types=1);

use Crontinel\Listeners\RecordScheduledTaskRun;
use Crontinel\Services\BackgroundRunContext;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Runs `schedule:run` in a real, throwaway Laravel app whose scheduled command is a child process, and returns the
 * receipts the scheduler spooled for it. $handle is the body of the command; $chain is how the task is scheduled.
 */
function runSchedulerFixture(string $handle, string $chain, string $config = ''): array
{
    $directory = sys_get_temp_dir().'/crontinel-foreground-'.bin2hex(random_bytes(12));
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/views'] as $subdirectory) {
        mkdir($directory.'/'.$subdirectory, 0700, true);
    }
    file_put_contents($directory.'/bootstrap/providers.php', '<?php return [];');
    $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);
    $script = <<<'PHP'
<?php
require AUTOLOAD;
class SyncOrders extends Illuminate\Console\Command
{
    protected $signature = 'orders:sync';

    public function handle(): int
    {
        HANDLE
        return 0;
    }
}
$app = Illuminate\Foundation\Application::configure(basePath: __DIR__)
    ->withExceptions()
    ->withProviders([Crontinel\CrontinelServiceProvider::class])
    ->withCommands([SyncOrders::class])
    ->withSchedule(function ($schedule) {
        $schedule->command('orders:sync')->everyMinute()->name('orders-sync')CHAIN;
    })->create();
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function () {
    config(['app.key'=>'base64:'.base64_encode(str_repeat('a', 32)), 'app.env'=>'testing',
        'crontinel.saas_key'=>null, 'crontinel.reporting.durable'=>true,
        'crontinel.reporting.spool_path'=>__DIR__.'/spool',
        'cache.default'=>'array', 'logging.default'=>'single'CONFIG]);
});

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
config(['crontinel.saas_key'=>'fixture-key']);
$status = $kernel->handle($input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput);
$kernel->terminate($input, $status);
exit($status);
PHP;
    file_put_contents($directory.'/artisan', str_replace(['AUTOLOAD', 'HANDLE', 'CHAIN', 'CONFIG'], [$autoload, $handle, $chain, $config], $script));
    try {
        $process = new Process([PHP_BINARY, 'artisan', 'schedule:run'], $directory, ['APP_ENV' => 'testing']);
        $process->setTimeout(20)->mustRun();
        $deadline = microtime(true) + 10;
        do {
            $records = array_map(fn ($path) => json_decode(file_get_contents($path), true)['payload'], glob($directory.'/spool/*.json') ?: []);
            $records = array_values(array_filter($records, fn ($record) => str_contains($record['command'], 'orders:sync') && $record['status'] === 'completed'));
            if ($records !== []) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        // The child's hand-off file is consumed, not left behind.
        expect(glob($directory.'/storage/framework/crontinel-outcomes/*.json') ?: [])->toBe([]);

        return $records;
    } finally {
        File::deleteDirectory($directory);
    }
}

it('carries a count recorded inside a scheduled command back to its receipt under default settings', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('POSIX shell acceptance fixture');
    }
    // The scheduler runs Schedule::command() as a child process. This app is real: a console command calls
    // Outcome::metric() and background correlation is off, the default every customer starts with.
    $records = runSchedulerFixture(<<<'HANDLE'
Crontinel\Outcome::metric('processed_records', 7);
        Crontinel\Outcome::timestamp('latest_artifact', '2026-11-12T12:00:00Z');
        Crontinel\Outcome::output('Imported 7 orders, skipped 2 with no email.');
HANDLE, '');

    expect($records)->toHaveCount(1);
    expect($records[0]['job_name'])->toBe('orders-sync');
    expect($records[0]['exit_code'])->toBe(0);
    expect($records[0]['outcomes']['metrics'])->toBe(['processed_records' => 7]);
    expect($records[0]['outcomes']['timestamps'])->toBe(['latest_artifact' => '2026-11-12T12:00:00Z']);
    expect($records[0]['output'])->toBe('Imported 7 orders, skipped 2 with no email.');
});

it('sends the output file of a scheduled command when capture is switched on', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('POSIX shell acceptance fixture');
    }
    // Opt-in: CRONTINEL_CAPTURE_OUTPUT with sendOutputTo(). The command only prints; it never calls Outcome::output().
    $records = runSchedulerFixture(
        '$this->line("Synced 5 orders");',
        "->sendOutputTo(__DIR__.'/orders.out')",
        ", 'crontinel.cron.capture_output'=>true",
    );

    expect($records)->toHaveCount(1);
    expect($records[0]['output'])->toContain('Synced 5 orders');
});

it('sends no output by default, because command output can hold customer data', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('POSIX shell acceptance fixture');
    }
    $records = runSchedulerFixture('$this->line("secret: hunter2");', "->sendOutputTo(__DIR__.'/orders.out')");

    expect($records)->toHaveCount(1);
    expect($records[0]['output'] ?? null)->toBeNull();
});

it('puts the run context in the environment only while a foreground task runs', function () {
    Http::fake();
    config(['crontinel.saas_key' => 'test-key']);
    $before = getenv(BackgroundRunContext::VARIABLE);
    $listener = new RecordScheduledTaskRun;
    $task = app(Schedule::class)->exec('foreground:command');

    $listener->handleStarting(new ScheduledTaskStarting($task));
    expect(getenv(BackgroundRunContext::VARIABLE))->toBeString()->not->toBe('');
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.001));

    expect(getenv(BackgroundRunContext::VARIABLE))->toBe($before);
    expect($_ENV[BackgroundRunContext::VARIABLE] ?? null)->toBeNull();
    expect($_SERVER[BackgroundRunContext::VARIABLE] ?? null)->toBeNull();
});

it('leaves the environment alone for a foreground task run as another user', function () {
    Http::fake();
    config(['crontinel.saas_key' => 'test-key']);
    $before = getenv(BackgroundRunContext::VARIABLE);
    $listener = new RecordScheduledTaskRun;
    $task = app(Schedule::class)->exec('sudo:command')->user('other');

    $listener->handleStarting(new ScheduledTaskStarting($task));
    expect(getenv(BackgroundRunContext::VARIABLE))->toBe($before);
    $listener->handleFinished(new ScheduledTaskFinished($task, 0.001));
});
