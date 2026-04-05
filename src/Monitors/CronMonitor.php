<?php

declare(strict_types=1);

namespace Crontinel\Monitors;

use Crontinel\Data\CronStatus;
use Crontinel\Models\CronRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

class CronMonitor
{
    public function __construct(private readonly Schedule $schedule) {}

    /**
     * @return CronStatus[]
     */
    public function all(): array
    {
        return collect($this->schedule->events())
            ->map(fn ($event) => $this->statusFor($event))
            ->values()
            ->all();
    }

    public function statusFor(mixed $event): CronStatus
    {
        $command    = $event->command ?? $event->description ?? 'unknown';
        $expression = $event->expression;
        $lastRun    = CronRun::latestFor($command);
        $nextDue    = $this->nextDue($event);

        $status = match (true) {
            $lastRun === null                       => 'never_run',
            $lastRun->exit_code !== 0               => 'failed',
            $this->isLate($lastRun, $nextDue)       => 'late',
            default                                 => 'ok',
        };

        return new CronStatus(
            command: $command,
            expression: $expression,
            status: $status,
            lastRunAt: $lastRun?->ran_at,
            lastExitCode: $lastRun?->exit_code,
            lastDurationMs: $lastRun?->duration_ms,
            nextDueAt: $nextDue,
        );
    }

    private function nextDue(mixed $event): Carbon
    {
        return Carbon::now()->next(
            fn (Carbon $date) => $event->isDue(app())
        );
    }

    private function isLate(?CronRun $lastRun, Carbon $nextDue): bool
    {
        if ($lastRun === null) {
            return false;
        }

        $threshold = config('cron-sentinel.cron.late_alert_after_seconds', 120);

        // If the next due time has passed by more than the threshold, we're late
        return now()->diffInSeconds($nextDue) > $threshold && $nextDue->isPast();
    }
}
