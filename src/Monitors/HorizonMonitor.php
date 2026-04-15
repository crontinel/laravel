<?php

declare(strict_types=1);

namespace Crontinel\Monitors;

use Crontinel\Data\HorizonStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HorizonMonitor
{
    public function status(): HorizonStatus
    {
        $supervisors = $this->getSupervisors();
        $masterStatus = $this->getMasterStatus();
        $failedJobsPerMinute = $this->getFailedJobsPerMinute();

        return new HorizonStatus(
            running: $masterStatus === 'running',
            supervisors: $supervisors,
            failedJobsPerMinute: $failedJobsPerMinute,
            pausedAt: $masterStatus === 'paused' ? now() : null,
            failedJobsPerMinuteThreshold: (float) config('crontinel.horizon.failed_jobs_per_minute_threshold', 5),
        );
    }

    private function getMasterStatus(): string
    {
        try {
            $connection = $this->resolveHorizonConnection();
            $masters = Redis::connection($connection)->smembers('horizon:masters');
            if (empty($masters)) {
                return 'stopped';
            }

            $master = reset($masters);
            $info = Redis::connection($connection)->hmget($master, ['status']);

            return $info[0] ?? 'unknown';
        } catch (\Throwable $e) {
            Log::warning('Crontinel: Could not reach Horizon Redis connection.', ['error' => $e->getMessage()]);

            return 'unavailable';
        }
    }

    private function getSupervisors(): array
    {
        try {
            $connection = $this->resolveHorizonConnection();
            $supervisors = [];
            $keys = Redis::connection($connection)->keys('horizon:supervisors:*');

            foreach ($keys as $key) {
                $data = Redis::connection($connection)->hmget($key, ['name', 'status', 'processes', 'queue']);
                $supervisors[] = [
                    'name' => $data[0] ?? $key,
                    'status' => $data[1] ?? 'unknown',
                    'processes' => (int) ($data[2] ?? 0),
                    'queue' => $data[3] ?? 'default',
                ];
            }

            return $supervisors;
        } catch (\Throwable $e) {
            Log::warning('Crontinel: Could not read Horizon supervisors.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getFailedJobsPerMinute(): float
    {
        try {
            $connection = $this->resolveHorizonConnection();
            $key = 'horizon:failed_jobs_per_minute';
            $value = Redis::connection($connection)->get($key);

            return (float) ($value ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function resolveHorizonConnection(): string
    {
        $connection = config('crontinel.horizon.connection', 'horizon');

        // Validate the connection exists before trying to use it
        $redisConfig = config('database.redis.'.$connection);

        if (! $redisConfig) {
            throw new \RuntimeException("Redis connection [{$connection}] is not configured.");
        }

        return $connection;
    }
}
