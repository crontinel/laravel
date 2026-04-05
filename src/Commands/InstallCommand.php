<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'crontinel:install';
    protected $description = 'Install Cron Sentinel: publish config and run migrations';

    public function handle(): int
    {
        $this->info('Installing Cron Sentinel...');

        $this->call('vendor:publish', ['--tag crontinel-config']);
        $this->call('migrate');

        $this->newLine();
        $this->info('✅ Cron Sentinel installed successfully.');
        $this->line('   Dashboard: <href='.config('app.url').'/'.config('cron-sentinel.path').'>'.config('app.url').'/'.config('cron-sentinel.path').'</>');
        $this->newLine();
        $this->line('Next: add <fg=yellow>Crontinel\Http\Middleware\CrontinelTracking</> to your scheduled commands to track cron runs.');

        return self::SUCCESS;
    }
}
