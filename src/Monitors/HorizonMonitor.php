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
            pausedAt: $masterStatus === 'paused' || collect($supervisors)->contains('status', 'paused') ? now() : null,
            failedJobsPerMinuteThreshold: (float) config('crontinel.horizon.failed_jobs_per_minute_threshold', 5),
        );
    }

    private function getMasterStatus(): string
    {
        try {
            $connection = $this->resolveHorizonConnection();
            // Horizon records active masters in a sorted set and refreshes their score.
            $masters = Redis::connection($connection)->zrevrangebyscore('masters', '+inf', time() - 14);
            if (empty($masters)) {
                return 'stopped';
            }

            $master = reset($masters);
            $info = Redis::connection($connection)->hmget('master:'.$master, ['name', 'status']);

            return array_values($info)[1] ?? 'unknown';
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
            $names = Redis::connection($connection)->zrevrangebyscore('supervisors', '+inf', time() - 29);

            foreach ($names as $name) {
                $data = Redis::connection($connection)->hmget('supervisor:'.$name, ['name', 'status', 'processes', 'options']);
                $data = array_values($data);
                if (empty($data[0])) {
                    continue;
                }

                $processes = json_decode((string) ($data[2] ?? ''), true);
                $options = json_decode((string) ($data[3] ?? ''), true);
                $queues = (array) ($options['queue'] ?? ['default']);
                $supervisors[] = [
                    'name' => $data[0],
                    'status' => $data[1] ?? 'unknown',
                    'processes' => is_array($processes) ? array_sum(array_map('intval', $processes)) : 0,
                    'queue' => implode(',', $queues),
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
            // Horizon scores failed jobs with the negative failure timestamp.
            $now = microtime(true);

            return (float) Redis::connection($connection)->zcount('failed_jobs', -$now, -($now - 60));
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
