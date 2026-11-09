<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class InstallCommand extends Command
{
    protected $signature = 'crontinel:install';

    protected $description = 'Install Crontinel: publish config and run migrations';

    public function handle(): int
    {
        $this->info('Installing Crontinel...');

        $this->call('vendor:publish', ['--tag' => 'crontinel-config']);
        $this->call('migrate');

        $dashboardUrl = rtrim(config('app.url', 'http://localhost'), '/')
            .'/'.config('crontinel.path', 'crontinel');

        $this->newLine();
        $this->info('✅ Crontinel installed successfully.');
        $this->line("   Dashboard: <href={$dashboardUrl}>{$dashboardUrl}</>");

        if (in_array('auth', config('crontinel.middleware', []), true) && ! Route::has('login')) {
            $this->newLine();
            $this->warn('This app has no login route, so the dashboard will return a 500. It needs a signed-in user, so add authentication first.');
        }

        $this->newLine();
        $this->comment('Cron run tracking is automatic — Crontinel listens to Laravel scheduler events.');
        $this->comment('No additional setup required.');
        $this->newLine();
        $this->line('To connect to the hosted SaaS, add to your .env:');
        $this->line('   <fg=yellow>CRONTINEL_API_KEY=your-api-key</>');

        return self::SUCCESS;
    }
}
