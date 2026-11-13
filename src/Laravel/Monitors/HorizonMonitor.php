<?php

declare(strict_types=1);

namespace Crontinel\Laravel\Monitors;

use Carbon\Carbon;
use Crontinel\Data\HorizonStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

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
            pausedAt: $this->pausedSince($masterStatus === 'paused' || collect($supervisors)->contains('status', 'paused')),
            failedJobsPerMinuteThreshold: (float) config('crontinel.horizon.failed_jobs_per_minute_threshold', 5),
        );
    }

    /**
     * Supervisors this environment's Horizon config starts that are not running, once they have been gone for
     * `supervisor_alert_after_seconds`. Horizon restarts a crashed supervisor within seconds, so a short gap is not
     * reported. Only checked while the master runs; a stopped or paused master already has its own alert.
     * shortcut: matches supervisors by name across all hosts (a second host's supervisor can hide a missing one) and
     * needs a cache store shared between checks, else a gap never lasts long enough to be reported.
     *
     * @return string[]
     */
    public function missingSupervisors(HorizonStatus $status): array
    {
        $key = 'crontinel:horizon:missing_since';

        try {
            $expected = $this->expectedSupervisors();
            if ($expected === [] || ! $status->running) {
                Cache::forget($key);

                return [];
            }

            $live = array_map(fn (array $supervisor) => substr((string) strrchr(':'.$supervisor['name'], ':'), 1), $status->supervisors);
            $missing = array_values(array_diff($expected, $live));
            if ($missing === []) {
                Cache::forget($key);

                return [];
            }

            $previous = (array) Cache::get($key, []);
            $since = [];
            foreach ($missing as $name) {
                $since[$name] = (int) ($previous[$name] ?? now()->getTimestamp());
            }
            Cache::forever($key, $since);

            $grace = (int) config('crontinel.horizon.supervisor_alert_after_seconds', 60);

            return array_values(array_filter($missing, fn (string $name) => now()->getTimestamp() - $since[$name] >= $grace));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Horizon starts the supervisors listed for the environment (the defaults only fill in their options).
     *
     * @return string[]
     */
    private function expectedSupervisors(): array
    {
        $environment = (string) config('horizon.env', app()->environment());
        $environments = (array) config('horizon.environments', []);
        $plan = $environments[$environment]
            ?? collect($environments)->first(fn ($supervisors, $pattern) => Str::is((string) $pattern, $environment));

        return array_map('strval', array_keys((array) $plan));
    }

    /**
     * Horizon does not record when it was paused, so the first check that saw it paused stands in for the start.
     * shortcut: needs a cache store shared between checks (not `array`), else every check reports "paused just now".
     */
    private function pausedSince(bool $paused): ?Carbon
    {
        $key = 'crontinel:horizon:paused_since';

        try {
            if (! $paused) {
                Cache::forget($key);

                return null;
            }

            $since = Cache::get($key);
            if (! is_string($since)) {
                Cache::forever($key, $since = now()->toIso8601String());
            }

            return Carbon::parse($since);
        } catch (\Throwable) {
            return $paused ? now() : null;
        }
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
