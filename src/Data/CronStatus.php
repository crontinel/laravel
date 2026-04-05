<?php

declare(strict_types=1);

namespace CronSentinel\Data;

use Illuminate\Support\Carbon;

final readonly class CronStatus
{
    public function __construct(
        public string $command,
        public string $expression,
        public string $status,  // 'ok' | 'failed' | 'late' | 'never_run'
        public ?Carbon $lastRunAt,
        public ?int $lastExitCode,
        public ?int $lastDurationMs,
        public ?Carbon $nextDueAt,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'ok';
    }
}
