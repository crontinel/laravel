<?php

declare(strict_types=1);

namespace Crontinel\Listeners;

use Crontinel\Models\CronRun;
use Crontinel\Services\SaasReporter;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;

class RecordScheduledTaskRun
{
    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $durationMs = (int) ($event->runtime * 1000);
        $finishedAt = now();
        $startedAt = $finishedAt->clone()->subMilliseconds($durationMs);

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
            startedAt: $startedAt->toIso8601String(),
            finishedAt: $finishedAt->toIso8601String(),
        );
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $output = $event->exception?->getMessage();
        $finishedAt = now();

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
            startedAt: $finishedAt->toIso8601String(),
            finishedAt: $finishedAt->toIso8601String(),
        );
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
