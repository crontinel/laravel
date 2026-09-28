<?php

declare(strict_types=1);

namespace Crontinel\Monitors;

use Crontinel\Data\QueueStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMonitor
{
    public function __construct(private readonly QueueManager $queueManager) {}

    /**
     * @return QueueStatus[]
     */
    public function all(): array
    {
        $watchList = config('crontinel.queues.watch', []);
        $connection = config('queue.default', 'sync');

        $queues = $this->resolveQueues($connection, $watchList);
        $driver = config("queue.connections.{$connection}.driver", 'sync');

        // Batch-fetch data for all queues in one query per driver type
        $depths = $this->batchDepths($driver, $connection, $queues);
        $oldestAges = $this->batchOldestJobAges($driver, $connection, $queues);
        $failedCounts = $this->batchFailedCounts($queues);

        return collect($queues)
            ->map(fn (string $queue) => new QueueStatus(
                connection: $connection,
                queue: $queue,
                depth: $depths[$queue] ?? 0,
                failedCount: $failedCounts[$queue] ?? 0,
                oldestJobAgeSeconds: $oldestAges[$queue] ?? null,
                depthThreshold: (int) config('crontinel.queues.depth_alert_threshold', 1000),
                waitTimeThresholdSeconds: (int) config('crontinel.queues.wait_time_alert_seconds', 300),
            ))
            ->values()
            ->all();
    }

    public function statusFor(string $connection, string $queue): QueueStatus
    {
        $driver = config("queue.connections.{$connection}.driver", 'sync');

        return new QueueStatus(
            connection: $connection,
            queue: $queue,
            depth: $this->getDepth($driver, $connection, $queue),
            failedCount: $this->getFailedCount($queue),
            oldestJobAgeSeconds: $this->getOldestJobAge($driver, $connection, $queue),
            depthThreshold: (int) config('crontinel.queues.depth_alert_threshold', 1000),
            waitTimeThresholdSeconds: (int) config('crontinel.queues.wait_time_alert_seconds', 300),
        );
    }

    private function getDepth(string $driver, string $connection, string $queue): int
    {
        try {
            return match ($driver) {
                'database' => $this->databaseJobs($connection)->where('queue', $queue)->count(),
                'redis' => $this->getRedisDepth($connection, $queue),
                default => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, int>
     */
    private function batchDepths(string $driver, string $connection, array $queues): array
    {
        if (empty($queues)) {
            return [];
        }

        try {
            return match ($driver) {
                'database' => $this->batchDatabaseDepths($connection, $queues),
                'redis' => $this->batchRedisDepths($connection, $queues),
                default => array_fill_keys($queues, 0),
            };
        } catch (\Throwable) {
            return array_fill_keys($queues, 0);
        }
    }

    /**
     * @return array<string, int>
     */
    private function batchDatabaseDepths(string $connection, array $queues): array
    {
        $rows = $this->databaseJobs($connection)
            ->whereIn('queue', $queues)
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->all();

        return array_merge(array_fill_keys($queues, 0), $rows);
    }

    /**
     * @return array<string, int>
     */
    private function batchRedisDepths(string $connection, array $queues): array
    {
        try {
            $redisConnection = config("queue.connections.{$connection}.connection", 'default');
            $results = Redis::connection($redisConnection)->pipeline(function ($pipe) use ($queues) {
                foreach ($queues as $queue) {
                    $pipe->llen("queues:{$queue}");
                    $pipe->zcard("queues:{$queue}:delayed");
                }
            });
            $depths = [];

            foreach ($queues as $i => $queue) {
                $pending = (int) ($results[$i * 2] ?? 0);
                $delayed = (int) ($results[$i * 2 + 1] ?? 0);
                $depths[$queue] = $pending + $delayed;
            }

            return $depths;
        } catch (\Throwable) {
            return array_fill_keys($queues, 0);
        }
    }

    private function getRedisDepth(string $connection, string $queue): int
    {
        try {
            $redisConnection = config("queue.connections.{$connection}.connection", 'default');

            $pending = (int) Redis::connection($redisConnection)->llen("queues:{$queue}");
            $delayed = (int) Redis::connection($redisConnection)->zcard("queues:{$queue}:delayed");

            return $pending + $delayed;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getFailedCount(string $queue): int
    {
        try {
            return DB::table('failed_jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function batchFailedCounts(array $queues): array
    {
        if (empty($queues)) {
            return [];
        }

        try {
            $rows = DB::table('failed_jobs')
                ->whereIn('queue', $queues)
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->all();

            // Fill missing queues with 0
            return array_merge(array_fill_keys($queues, 0), $rows);
        } catch (\Throwable) {
            return array_fill_keys($queues, 0);
        }
    }

    private function getOldestJobAge(string $driver, string $connection, string $queue): ?int
    {
        try {
            return match ($driver) {
                'database' => $this->getDatabaseOldestJobAge($connection, $queue),
                'redis' => $this->getRedisOldestJobAge($connection, $queue),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function batchOldestJobAges(string $driver, string $connection, array $queues): array
    {
        if (empty($queues)) {
            return [];
        }

        try {
            return match ($driver) {
                'database' => $this->batchDatabaseOldestJobAges($connection, $queues),
                'redis' => $this->batchRedisOldestJobAges($connection, $queues),
                default => array_fill_keys($queues, null),
            };
        } catch (\Throwable) {
            return array_fill_keys($queues, null);
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function batchDatabaseOldestJobAges(string $connection, array $queues): array
    {
        // Single query: get oldest created_at per queue
        $rows = $this->databaseJobs($connection)
            ->whereIn('queue', $queues)
            ->select('queue', DB::raw('MIN(created_at) as oldest_at'))
            ->groupBy('queue')
            ->pluck('oldest_at', 'queue')
            ->all();

        $result = [];
        foreach ($queues as $queue) {
            if (isset($rows[$queue])) {
                $result[$queue] = max(0, time() - (int) $rows[$queue]);
            } else {
                $result[$queue] = null;
            }
        }

        return $result;
    }

    /**
     * @return array<string, int|null>
     */
    private function batchRedisOldestJobAges(string $connection, array $queues): array
    {
        try {
            $redisConnection = config("queue.connections.{$connection}.connection", 'default');
            $results = Redis::connection($redisConnection)->pipeline(function ($pipe) use ($queues) {
                foreach ($queues as $queue) {
                    $pipe->lindex("queues:{$queue}", -1);
                }
            });

            $ages = [];
            foreach ($queues as $i => $queue) {
                $raw = $results[$i] ?? null;
                if (! $raw) {
                    $ages[$queue] = null;

                    continue;
                }

                $payload = json_decode($raw, true);
                $pushedAt = $payload['pushedAt'] ?? null;
                $ages[$queue] = $pushedAt ? (int) (time() - (int) $pushedAt) : null;
            }

            return $ages;
        } catch (\Throwable) {
            return array_fill_keys($queues, null);
        }
    }

    private function getDatabaseOldestJobAge(string $connection, string $queue): ?int
    {
        $oldest = $this->databaseJobs($connection)
            ->where('queue', $queue)
            ->orderBy('created_at')
            ->value('created_at');

        return $oldest ? max(0, time() - (int) $oldest) : null;
    }

    private function getRedisOldestJobAge(string $connection, string $queue): ?int
    {
        try {
            $redisConnection = config("queue.connections.{$connection}.connection", 'default');
            $raw = Redis::connection($redisConnection)->lindex("queues:{$queue}", -1);

            if (! $raw) {
                return null;
            }

            $payload = json_decode($raw, true);
            $pushedAt = $payload['pushedAt'] ?? null;

            return $pushedAt ? (int) (time() - (int) $pushedAt) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private function resolveQueues(string $connection, array $watchList): array
    {
        if (! empty($watchList)) {
            return $watchList;
        }

        $driver = config("queue.connections.{$connection}.driver", 'sync');

        try {
            if ($driver === 'database') {
                $queues = $this->databaseJobs($connection)->distinct()->pluck('queue')->all();

                return ! empty($queues) ? $queues : ['default'];
            }

            if ($driver === 'redis') {
                $redisConnection = config("queue.connections.{$connection}.connection", 'default');
                $keys = Redis::connection($redisConnection)->keys('queues:*');
                $prefix = (string) config('database.redis.options.prefix', '');

                $queues = collect($keys)
                    ->map(fn ($key) => str_starts_with((string) $key, $prefix) ? substr((string) $key, strlen($prefix)) : (string) $key)
                    ->map(fn ($key) => preg_replace('/^queues:(.+?)(:delayed|:reserved)?$/', '$1', $key))
                    ->unique()
                    ->filter(fn ($q) => ! str_contains((string) $q, ':'))
                    ->values()
                    ->all();

                return ! empty($queues) ? $queues : ['default'];
            }
        } catch (\Throwable) {
        }

        return ['default'];
    }

    private function databaseJobs(string $connection): Builder
    {
        $database = config("queue.connections.{$connection}.connection");
        $table = config("queue.connections.{$connection}.table", 'jobs');

        return DB::connection($database)->table($table);
    }
}
