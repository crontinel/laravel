<?php

declare(strict_types=1);

namespace Crontinel\Commands;

use Crontinel\Services\AgentService;
use Illuminate\Console\Command;

class AgentCommand extends Command
{
    protected $signature = 'crontinel:agent
                            {--systemd : Output systemd unit file and exit}
                            {--supervisor : Output supervisor config and exit}';

    protected $description = 'Start the Crontinel agent daemon — connects to app.crontinel.com via SSE to receive and execute commands';

    public function handle(AgentService $agent): int
    {
        if ($this->option('systemd')) {
            return $this->showSystemdConfig();
        }

        if ($this->option('supervisor')) {
            return $this->showSupervisorConfig();
        }

        if (! $agent->isConfigured()) {
            $this->warn('CRONTINEL_API_KEY is not set. Set it in your .env file to use the agent.');
            $this->line('');
            $this->line('  CRONTINEL_API_KEY=your-api-key');
            $this->line('  CRONTINEL_API_URL=https://app.crontinel.com (optional)');
            $this->line('');
            $this->line('You can also generate a systemd unit or supervisor config:');
            $this->line('  php artisan crontinel:agent --systemd');
            $this->line('  php artisan crontinel:agent --supervisor');

            return self::FAILURE;
        }

        $agent->setOutputWriter(function (string $message): void {
            $this->line($message);
        });

        $this->line('Crontinel Agent');
        $this->line('--------------');
        $this->line('');
        $this->line('Starting agent daemon. Press Ctrl+C to stop.');
        $this->line('');

        $agent->run();

        return self::SUCCESS;
    }

    private function showSystemdConfig(): int
    {
        $appPath = base_path();
        $phpBinary = defined('ARTISAN_PHP') ? ARTISAN_PHP : '/usr/bin/php';
        $user = function_exists('get_current_user') ? get_current_user() : 'www-data';

        $this->line('[Unit]');
        $this->line('Description=Crontinel Agent — remote command execution daemon');
        $this->line('After=network.target');
        $this->line('');
        $this->line('[Service]');
        $this->line("Type=simple");
        $this->line("User={$user}");
        $this->line("WorkingDirectory={$appPath}");
        $this->line("ExecStart={$phpBinary} artisan crontinel:agent");
        $this->line('Restart=always');
        $this->line('RestartSec=5');
        $this->line('TimeoutStopSec=30');
        $this->line('KillSignal=SIGTERM');
        $this->line('');
        $this->line('[Install]');
        $this->line('WantedBy=multi-user.target');
        $this->newLine();
        $this->line('# Save to /etc/systemd/system/crontinel-agent.service');
        $this->line('# Then: sudo systemctl daemon-reload && sudo systemctl enable --now crontinel-agent');

        return self::SUCCESS;
    }

    private function showSupervisorConfig(): int
    {
        $appPath = base_path();
        $phpBinary = defined('ARTISAN_PHP') ? ARTISAN_PHP : '/usr/bin/php';
        $user = function_exists('get_current_user') ? get_current_user() : 'www-data';

        $this->line('[program:crontinel-agent]');
        $this->line("command={$phpBinary} artisan crontinel:agent");
        $this->line("directory={$appPath}");
        $this->line("user={$user}");
        $this->line('autostart=true');
        $this->line('autorestart=true');
        $this->line('startretries=3');
        $this->line('stopwaitsecs=30');
        $this->line('stopsignal=SIGTERM');
        $this->line('stdout_logfile=/var/log/crontinel-agent.log');
        $this->line('stderr_logfile=/var/log/crontinel-agent.err');
        $this->newLine();
        $this->line('# Save to /etc/supervisor/conf.d/crontinel-agent.conf');
        $this->line('# Then: sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start crontinel-agent');

        return self::SUCCESS;
    }
}
