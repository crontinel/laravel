<?php

declare(strict_types=1);

namespace CronSentinel\Monitors;

use CronSentinel\Data\QueueStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\QueueManager;

class QueueMonitor
{
    public function __construct(private readonly QueueManager $queueManager) {}

    /**
     * @return QueueStatus[]
     */
    public function all(): array
    {
        $watchList = config('cron-sentinel.queues.watch', []);
        $connection = config('queue.default');

        return collect($this->resolveQueues($connection, $watchList))
            ->map(fn (string $queue) => $this->statusFor($connection, $queue))
            ->values()
            ->all();
    }

    public function statusFor(string $connection, string $queue): QueueStatus
    {
        $depth        = $this->getDepth($connection, $queue);
        $failedCount  = $this->getFailedCount($queue);
        $oldestJob    = $this->getOldestJobAge($connection, $queue);

        return new QueueStatus(
            connection: $connection,
            queue: $queue,
            depth: $depth,
            failedCount: $failedCount,
            oldestJobAgeSeconds: $oldestJob,
        );
    }

    private function getDepth(string $connection, string $queue): int
    {
        try {
            $driver = config("queue.connections.{$connection}.driver");

            if ($driver === 'database') {
                return DB::table('jobs')
                    ->where('queue', $queue)
                    ->count();
            }

            // Redis: use Horizon's queue size key if available
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getFailedCount(string $queue): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('queue', $queue)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getOldestJobAge(string $connection, string $queue): ?int
    {
        try {
            $driver = config("queue.connections.{$connection}.driver");

            if ($driver === 'database') {
                $oldest = DB::table('jobs')
                    ->where('queue', $queue)
                    ->orderBy('created_at')
                    ->value('created_at');

                return $oldest ? now()->diffInSeconds($oldest) : null;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveQueues(string $connection, array $watchList): array
    {
        if (! empty($watchList)) {
            return $watchList;
        }

        // Auto-discover queues from the jobs table
        try {
            $driver = config("queue.connections.{$connection}.driver");

            if ($driver === 'database') {
                return DB::table('jobs')
                    ->distinct()
                    ->pluck('queue')
                    ->all();
            }
        } catch (\Throwable) {}

        return ['default'];
    }
}
