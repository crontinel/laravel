<?php

declare(strict_types=1);

namespace Crontinel\Listeners;

use Crontinel\Models\CronRun;
use Crontinel\Services\SaasReporter;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;

class RecordScheduledTaskRun
{
    /** @var array<string, string> Task command => startedAt ISO8601 */
    private array $taskStartTimes = [];

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $this->taskStartTimes[$command] = now()->toIso8601String();
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $durationMs = (int) ($event->runtime * 1000);
        $finishedAt = now();
        $startedAt = $this->taskStartTimes[$command] ?? $finishedAt->clone()->subMilliseconds($durationMs)->toIso8601String();

        CronRun::record(
            command: $command,
            exitCode: 0,
            durationMs: $durationMs,
            output: null,
        );

        $this->pruneOldRuns();

        app(SaasReporter::class)->reportCronRun(
            command: $command,
            exitCode: 0,
            durationMs: $durationMs,
            output: null,
            startedAt: $startedAt,
            finishedAt: $finishedAt->toIso8601String(),
        );

        unset($this->taskStartTimes[$command]);
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $output = $event->exception?->getMessage();
        $finishedAt = now()->toIso8601String();
        $startedAt = $this->taskStartTimes[$command] ?? $finishedAt;

        CronRun::record(
            command: $command,
            exitCode: 1,
            durationMs: 0,
            output: $output,
        );

        app(SaasReporter::class)->reportCronRun(
            command: $command,
            exitCode: 1,
            durationMs: 0,
            output: $output,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );

        unset($this->taskStartTimes[$command]);
    }

    private function resolveCommand(mixed $task): string
    {
        return $task->command
            ?? $task->description
            ?? (string) $task;
    }

    private function pruneOldRuns(): void
    {
        $retainDays = config('crontinel.cron.retain_days', 30);

        CronRun::where('ran_at', '<', now()->subDays($retainDays))->delete();
    }
}
