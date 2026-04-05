<?php

declare(strict_types=1);

namespace Crontinel\Listeners;

use Crontinel\Models\CronRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;

class RecordScheduledTaskRun
{
    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        CronRun::record(
            command: $this->resolveCommand($event->task),
            exitCode: 0,
            durationMs: (int) ($event->runtime * 1000),
            output: null,
        );

        $this->pruneOldRuns();
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        CronRun::record(
            command: $this->resolveCommand($event->task),
            exitCode: 1,
            durationMs: 0,
            output: $event->exception?->getMessage(),
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
