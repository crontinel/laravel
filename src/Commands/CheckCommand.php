<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Crontinel\Monitors\CronMonitor;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature   = 'crontinel:check {--format=table : Output format (table|json)}';
    protected $description = 'Check the current health of Horizon, queues, and cron jobs';

    public function handle(
        HorizonMonitor $horizon,
        QueueMonitor $queue,
        CronMonitor $cron,
    ): int {
        $horizonStatus = config('cron-sentinel.horizon.enabled') ? $horizon->status() : null;
        $queueStatuses = config('cron-sentinel.queues.enabled') ? $queue->all() : [];
        $cronStatuses  = config('cron-sentinel.cron.enabled') ? $cron->all() : [];

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'horizon' => $horizonStatus,
                'queues'  => $queueStatuses,
                'cron'    => $cronStatuses,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Horizon
        if ($horizonStatus) {
            $this->newLine();
            $this->line('<fg=blue;options=bold>Horizon</>');
            $this->table(
                ['Status', 'Supervisors', 'Failed/min'],
                [[
                    $horizonStatus->running ? '<fg=green>running</>' : '<fg=red>stopped</>',
                    count($horizonStatus->supervisors),
                    $horizonStatus->failedJobsPerMinute,
                ]]
            );
        }

        // Queues
        if (! empty($queueStatuses)) {
            $this->line('<fg=blue;options=bold>Queues</>');
            $this->table(
                ['Queue', 'Depth', 'Failed', 'Oldest (s)', 'Health'],
                collect($queueStatuses)->map(fn ($s) => [
                    $s->queue,
                    $s->depth,
                    $s->failedCount,
                    $s->oldestJobAgeSeconds ?? '-',
                    $s->isHealthy() ? '<fg=green>✓ ok</>' : '<fg=red>✗ alert</>',
                ])->all()
            );
        }

        // Cron
        if (! empty($cronStatuses)) {
            $this->line('<fg=blue;options=bold>Cron Jobs</>');
            $this->table(
                ['Command', 'Schedule', 'Last Run', 'Status'],
                collect($cronStatuses)->map(fn ($s) => [
                    $s->command,
                    $s->expression,
                    $s->lastRunAt?->diffForHumans() ?? 'never',
                    match ($s->status) {
                        'ok'        => '<fg=green>✓ ok</>',
                        'failed'    => '<fg=red>✗ failed</>',
                        'late'      => '<fg=yellow>⚠ late</>',
                        'never_run' => '<fg=gray>- never run</>',
                        default     => $s->status,
                    },
                ])->all()
            );
        }

        $hasAlerts = ($horizonStatus && ! $horizonStatus->isHealthy())
            || collect($queueStatuses)->some(fn ($s) => ! $s->isHealthy())
            || collect($cronStatuses)->some(fn ($s) => ! $s->isHealthy());

        return $hasAlerts ? self::FAILURE : self::SUCCESS;
    }
}
