<?php

declare(strict_types=1);

namespace Crontinel\Listeners;

use Crontinel\Models\CronRun;
use Crontinel\Services\SaasReporter;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use WeakMap;

class RecordScheduledTaskRun
{
    private WeakMap $runs;

    public function __construct()
    {
        $this->runs = new WeakMap;
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        if (! config('crontinel.cron.enabled', true) || $event->task->runInBackground) {
            return;
        }

        $this->safely(function () use ($event) {
            $run = ['key' => (string) Str::uuid(), 'start' => now()->toIso8601String()];
            $this->runs[$event->task] = $run;
            app(SaasReporter::class)->reportCronStarted($this->resolveCommand($event->task), $run['start'], $run['key']);
        });
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        // Laravel emits this when a background process is launched, not completed.
        if ($event->task->runInBackground) {
            return;
        }
        $this->finish($event->task, $event->task->exitCode ?? 0, (int) ($event->runtime * 1000));
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->finish($event->task, 1, 0, $event->exception?->getMessage());
    }

    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        // Completion arrives in another process; background runs are terminal-only.
        $this->finish($event->task, $event->task->exitCode ?? 0, 0);
    }

    private function finish(object $task, int $exitCode, int $durationMs, ?string $output = null): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $this->safely(function () use ($task, $exitCode, $durationMs, $output) {
            $run = $this->runs[$task] ?? [
                'key' => (string) Str::uuid(),
                'start' => now()->subMilliseconds($durationMs)->toIso8601String(),
            ];
            if (isset($run['finished'])) {
                return;
            }
            $run['finished'] = now()->toIso8601String();
            $this->runs[$task] = $run;
            $command = $this->resolveCommand($task);

            // Local storage failure must not prevent SaaS reporting or fail the job.
            $this->safely(fn () => CronRun::record($command, $exitCode, $durationMs, $output));
            app(SaasReporter::class)->reportCronRun(
                $command, $exitCode, $durationMs, $output, $run['start'], $run['finished'], $run['key'],
            );
        });
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            try {
                Log::warning('Crontinel: scheduled task monitoring failed', ['exception' => $e::class]);
            } catch (\Throwable) {
                // Monitoring must not break the application's scheduled task.
            }
        }
    }

    private function resolveCommand(mixed $task): string
    {
        return $task->command ?? $task->description ?? (string) $task;
    }
}
