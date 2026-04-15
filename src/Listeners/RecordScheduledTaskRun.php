<?php

declare(strict_types=1);

namespace Crontinel\Listeners;

use Crontinel\Models\CronRun;
use Crontinel\Services\SaasReporter;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Cache;

class RecordScheduledTaskRun
{
    private const START_TIME_PREFIX = 'crontinel_task_start:';

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        Cache::put(self::START_TIME_PREFIX.$command, now()->toIso8601String(), 300);
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $command = $this->resolveCommand($event->task);
        $durationMs = (int) ($event->runtime * 1000);
        $finishedAt = now();
        $startedAt = Cache::pull(self::START_TIME_PREFIX.$command)
            ?? $finishedAt->clone()->subMilliseconds($durationMs)->toIso8601String();

        CronRun::record(
            command: $command,
            exitCode: 0,
            durationMs: $durationMs,
            output: null,
        );

        app(SaasReporter::class)->reportCronRun(
            command: $command,
            exitCode: 0,
            durationMs: $durationMs,
            output: null,
            startedAt: $startedAt,
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
        $finishedAt = now()->toIso8601String();
        $startedAt = Cache::pull(self::START_TIME_PREFIX.$command) ?? $finishedAt;

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
    }

    private function resolveCommand(mixed $task): string
    {
        return $task->command
            ?? $task->description
            ?? (string) $task;
    }
}
