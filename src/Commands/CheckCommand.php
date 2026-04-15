<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Monitors\CronMonitor;
use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Crontinel\Services\AlertService;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'crontinel:check {--format=table : Output format (table|json)} {--no-alerts : Skip firing alerts}';

    protected $description = 'Check the current health of Horizon, queues, and cron jobs';

    public function handle(
        HorizonMonitor $horizon,
        QueueMonitor $queue,
        CronMonitor $cron,
        AlertService $alerts,
    ): int {
        $horizonStatus = config('crontinel.horizon.enabled') ? $horizon->status() : null;
        $queueStatuses = config('crontinel.queues.enabled') ? $queue->all() : [];
        $cronStatuses = config('crontinel.cron.enabled') ? $cron->all() : [];

        // Fire alerts unless suppressed
        if (! $this->option('no-alerts')) {
            if ($horizonStatus) {
                $alerts->evaluateHorizon($horizonStatus);
            }

            foreach ($queueStatuses as $queueStatus) {
                $alerts->evaluateQueue($queueStatus);
            }

            foreach ($cronStatuses as $cronStatus) {
                $alerts->evaluateCron($cronStatus);
            }
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'horizon' => $horizonStatus,
                'queues' => $queueStatuses,
                'crons' => $cronStatuses,
            ], JSON_PRETTY_PRINT));

            return $this->hasAlerts($horizonStatus, $queueStatuses, $cronStatuses)
                ? self::FAILURE
                : self::SUCCESS;
        }

        // Horizon
        if ($horizonStatus) {
            $this->newLine();
            $this->line('<fg=blue;options=bold>Horizon</>');
            $this->table(
                ['Status', 'Supervisors', 'Failed/min', 'Health'],
                [[
                    $horizonStatus->running ? '<fg=green>running</>' : '<fg=red>stopped</>',
                    count($horizonStatus->supervisors),
                    number_format($horizonStatus->failedJobsPerMinute, 1),
                    $horizonStatus->isHealthy() ? '<fg=green>✓ ok</>' : '<fg=red>✗ alert</>',
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
                    number_format($s->depth),
                    $s->failedCount,
                    $s->oldestJobAgeSeconds ?? '-',
                    $s->isHealthy() ? '<fg=green>✓ ok</>' : '<fg=red>✗ alert</>',
                ])->all()
            );
        }

        // Cron
        if (! empty($cronStatuses)) {
            $this->line('<fg=blue;options=bold>Scheduled Commands</>');
            $this->table(
                ['Command', 'Schedule', 'Last Run', 'Duration', 'Status'],
                collect($cronStatuses)->map(fn ($s) => [
                    $s->command,
                    $s->expression,
                    $s->lastRunAt?->diffForHumans() ?? 'never',
                    $s->lastDurationMs ? $s->lastDurationMs.'ms' : '-',
                    match ($s->status) {
                        'ok' => '<fg=green>✓ ok</>',
                        'failed' => '<fg=red>✗ failed</>',
                        'late' => '<fg=yellow>⚠ late</>',
                        'never_run' => '<fg=gray>– never run</>',
                        default => $s->status,
                    },
                ])->all()
            );
        }

        $hasAlerts = $this->hasAlerts($horizonStatus, $queueStatuses, $cronStatuses);

        if ($hasAlerts) {
            $this->newLine();
            $this->line('<fg=red>✗ One or more monitors are in alert state.</>');
        } else {
            $this->newLine();
            $this->line('<fg=green>✓ All monitors healthy.</>');
        }

        return $hasAlerts ? self::FAILURE : self::SUCCESS;
    }

    private function hasAlerts(?object $horizonStatus, array $queueStatuses, array $cronStatuses): bool
    {
        return ($horizonStatus && ! $horizonStatus->isHealthy())
            || collect($queueStatuses)->some(fn ($s) => ! $s->isHealthy())
            || collect($cronStatuses)->some(fn ($s) => ! $s->isHealthy());
    }
}
