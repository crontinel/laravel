<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Models\CronRun;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'crontinel:prune {--days= : Retain runs newer than this many days (default: from config)}';

    protected $description = 'Delete old cron run records beyond the configured retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('crontinel.cron.retain_days', 30));

        $deleted = CronRun::where('ran_at', '<', now()->subDays($days))->delete();

        $this->line("Pruned {$deleted} cron run record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
