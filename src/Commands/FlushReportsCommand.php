<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Services\SaasReporter;
use Illuminate\Console\Command;

class FlushReportsCommand extends Command
{
    protected $signature = 'crontinel:flush-reports';

    protected $description = 'Drain one bounded batch of durable cron reports and print delivery counts';

    public function handle(SaasReporter $reporter): int
    {
        $result = $reporter->flushCronReports();
        if ($result === null) {
            $this->warn('Set CRONTINEL_API_KEY and CRONTINEL_DURABLE_REPORTING=true to drain reports.');

            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['failed'] || $result['discarded'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
