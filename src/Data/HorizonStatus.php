<?php

declare(strict_types=1);

namespace CronSentinel\Data;

use Illuminate\Support\Carbon;

final readonly class HorizonStatus
{
    public function __construct(
        public bool $running,
        public array $supervisors,
        public float $failedJobsPerMinute,
        public ?Carbon $pausedAt,
    ) {}

    public function isHealthy(): bool
    {
        $threshold = config('cron-sentinel.horizon.failed_jobs_per_minute_threshold', 5);

        return $this->running
            && $this->pausedAt === null
            && $this->failedJobsPerMinute < $threshold;
    }
}
