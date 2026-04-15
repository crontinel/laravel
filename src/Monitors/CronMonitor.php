<?php

declare(strict_types=1);

namespace Crontinel\Monitors;

use Cron\CronExpression;
use Crontinel\Data\CronStatus;
use Crontinel\Models\CronRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
        $command = $event->command ?? $event->description ?? 'unknown';
        $expression = $event->expression;
        $lastRun = CronRun::latestFor($command);
        $nextDue = $this->nextDue($expression);
        $previousDue = $this->previousDue($expression);

        $status = match (true) {
            $lastRun === null => $this->isPastFirstWindow($nextDue) ? 'never_run' : 'ok',
            $lastRun->exit_code !== 0 => 'failed',
            $this->isLate($lastRun, $previousDue) => 'late',
            default => 'ok',
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

    private function nextDue(string $expression): ?Carbon
    {
        try {
            $expr = new CronExpression($expression);

            return Carbon::instance($expr->getNextRunDate());
        } catch (\Throwable $e) {
            Log::warning('Crontinel: Invalid cron expression.', ['expression' => $expression, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function previousDue(string $expression): ?Carbon
    {
        try {
            $expr = new CronExpression($expression);

            return Carbon::instance($expr->getPreviousRunDate());
        } catch (\Throwable $e) {
            Log::warning('Crontinel: Invalid cron expression.', ['expression' => $expression, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function isPastFirstWindow(?Carbon $nextDue): bool
    {
        // If next due hasn't arrived yet, this is a new command — don't alert on first deploy
        if ($nextDue === null) {
            return true; // no schedule, treat as needing attention
        }

        return now()->gte($nextDue);
    }

    private function isLate(?CronRun $lastRun, ?Carbon $previousDue): bool
    {
        if ($lastRun === null || $previousDue === null) {
            return false;
        }

        // Not late if the last run happened after the previous scheduled time
        if ($lastRun->ran_at->gte($previousDue)) {
            return false;
        }

        // Late if we're past the grace period since the previous due time
        $threshold = config('crontinel.cron.late_alert_after_seconds', 120);

        return now()->diffInSeconds($previousDue, absolute: true) > $threshold;
    }
}
