<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Services\SaasReporter;
use Illuminate\Console\Command;

class ReportCommand extends Command
{
    protected $signature = 'crontinel:report';

    protected $description = 'Send a status ping to crontinel.com (called automatically every minute when CRONTINEL_API_KEY is set)';

    public function handle(SaasReporter $reporter): int
    {
        if (empty(config('crontinel.saas_key'))) {
            $this->warn('CRONTINEL_API_KEY is not set — skipping SaaS report.');

            return self::SUCCESS;
        }

        $reporter->reportStatus();
        $reports = $reporter->flushCronReports();
        if ($reports !== null) {
            $this->line('Cron reports: '.json_encode($reports, JSON_THROW_ON_ERROR));
        }

        $this->line('Status reported to crontinel.com.');

        return self::SUCCESS;
    }
}
