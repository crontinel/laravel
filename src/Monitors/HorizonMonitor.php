<?php

declare(strict_types=1);

namespace Crontinel\Monitors;

use Crontinel\Data\HorizonStatus;
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
        );
    }

    private function getMasterStatus(): string
    {
        try {
            $masters = Redis::connection('horizon')->smembers('horizon:masters');
            if (empty($masters)) {
                return 'stopped';
            }

            $master = reset($masters);
            $info = Redis::connection('horizon')->hmget($master, ['status']);

            return $info[0] ?? 'unknown';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    private function getSupervisors(): array
    {
        try {
            $supervisors = [];
            $keys = Redis::connection('horizon')->keys('horizon:supervisors:*');

            foreach ($keys as $key) {
                $data = Redis::connection('horizon')->hmget($key, ['name', 'status', 'processes', 'queue']);
                $supervisors[] = [
                    'name'      => $data[0] ?? $key,
                    'status'    => $data[1] ?? 'unknown',
                    'processes' => (int) ($data[2] ?? 0),
                    'queue'     => $data[3] ?? 'default',
                ];
            }

            return $supervisors;
        } catch (\Throwable) {
            return [];
        }
    }

    private function getFailedJobsPerMinute(): float
    {
        try {
            $key = 'horizon:failed_jobs_per_minute';
            $value = Redis::connection('horizon')->get($key);

            return (float) ($value ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
