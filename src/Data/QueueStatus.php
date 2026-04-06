<?php

declare(strict_types=1);

namespace Crontinel\Data;

final readonly class QueueStatus
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $depth,
        public int $failedCount,
        public ?int $oldestJobAgeSeconds,
    ) {}

    public function isHealthy(): bool
    {
        $depthThreshold = config('crontinel.queues.depth_alert_threshold', 1000);
        $waitTimeThreshold = config('crontinel.queues.wait_time_alert_seconds', 300);

        if ($this->depth > $depthThreshold) {
            return false;
        }

        if ($this->oldestJobAgeSeconds !== null && $this->oldestJobAgeSeconds > $waitTimeThreshold) {
            return false;
        }

        return true;
    }
}
