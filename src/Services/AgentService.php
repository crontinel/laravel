<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AgentService
{
    private const REGISTER_PATH = '/v1/agents/register';

    private const HEARTBEAT_PATH = '/v1/agents/heartbeat';

    private const COMMANDS_PATH = '/v1/agents/%s/commands';

    private const RESULT_PATH = '/v1/ingest/agent-result';

    /** The hosted app keeps at most this many characters of output. */
    private const MAX_OUTPUT = 10000;

    private bool $running = true;

    private ?int $appId = null;

    private float $lastHeartbeatAt = 0.0;

    private int $failures = 0;

    private ?\Closure $outputWriter = null;

    public function setOutputWriter(\Closure $writer): void
    {
        $this->outputWriter = $writer;
    }

    /**
     * Run the agent until it receives a stop signal: register, then poll for commands.
     */
    public function run(): void
    {
        $this->installSignalHandlers();
        $this->writeOutput('Agent starting as '.$this->agentId().'...');

        while ($this->running) {
            try {
                if ($this->appId === null) {
                    $this->register();
                    $this->writeOutput('Registered. Polling for commands...');
                }

                $this->pollOnce();

                if ($this->isHeartbeatDue()) {
                    $this->sendHeartbeat();
                }

                $this->failures = 0;
                $this->pause((int) config('crontinel.agent.poll_interval', 5));
            } catch (\Throwable $e) {
                $this->failures++;
                $delay = min(1 << min($this->failures - 1, 6), (int) config('crontinel.agent.max_reconnect_delay', 60));
                Log::warning('Crontinel Agent: '.$e->getMessage());
                $this->writeOutput("Error: {$e->getMessage()}. Retrying in {$delay}s...");
                $this->pause($delay);
            }
        }

        $this->writeOutput('Agent stopped.');
    }

    /**
     * Register this host with the hosted app and learn which app the API key belongs to.
     */
    public function register(): void
    {
        $response = $this->http()->post($this->saasUrl(self::REGISTER_PATH), [
            'agent_id' => $this->agentId(),
            'agent_name' => config('app.name'),
            'metadata' => ['hostname' => gethostname() ?: null, 'php' => PHP_VERSION],
        ])->throw();

        $appId = $response->json('app_id');

        if (! is_int($appId)) {
            throw new \RuntimeException('The Crontinel app did not return an app_id. Check CRONTINEL_API_URL.');
        }

        $this->appId = $appId;
        $this->lastHeartbeatAt = microtime(true);
    }

    /**
     * Fetch pending commands and run each one.
     */
    public function pollOnce(): void
    {
        $response = $this->http()->get($this->saasUrl(sprintf(self::COMMANDS_PATH, $this->appId)))->throw();

        foreach ($response->json('data') ?? [] as $trigger) {
            if (! $this->running) {
                return;
            }

            if (is_array($trigger)) {
                $this->handleCommand($trigger);
            }
        }
    }

    /**
     * Run one command from the hosted app and report the result.
     *
     * @param  array{id?: mixed, command?: mixed, payload?: mixed}  $trigger
     */
    public function handleCommand(array $trigger): void
    {
        if (! isset($trigger['id'], $trigger['command']) || ! is_string($trigger['command'])) {
            Log::warning('Crontinel Agent: malformed command', ['trigger' => $trigger]);

            return;
        }

        $triggerId = (string) $trigger['id'];
        $commandString = $trigger['command'];
        $payload = is_array($trigger['payload'] ?? null) ? $trigger['payload'] : [];

        if (! $this->isCommandAllowed($commandString)) {
            Log::warning('Crontinel Agent: command rejected by allowlist', [
                'trigger_id' => $triggerId,
                'command' => $commandString,
            ]);
            $this->writeOutput("Command [{$triggerId}] rejected: not in allowlist");
            $this->reportResult($triggerId, 'failed', -1, '', 'Command rejected: not in allowlist');

            return;
        }

        $this->writeOutput("Executing command [{$triggerId}]: {$commandString}");
        $start = microtime(true);

        try {
            $process = Process::fromShellCommandline(
                command: $commandString,
                env: is_array($payload['env'] ?? null) ? $payload['env'] : [],
                timeout: (float) ($payload['timeout'] ?? config('crontinel.agent.default_command_timeout', 300)),
            );
            $process->run();

            $exitCode = (int) $process->getExitCode();
            $status = $exitCode === 0 ? 'completed' : 'failed';
            $output = $process->getOutput().$process->getErrorOutput();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $this->writeOutput("Command [{$triggerId}] finished: {$status} ({$durationMs}ms, exit {$exitCode})");
            $this->reportResult($triggerId, $status, $exitCode, $output, $status === 'failed' ? "Exited with code {$exitCode}" : null);
        } catch (\Throwable $e) {
            $this->writeOutput("Command [{$triggerId}] error: {$e->getMessage()}");
            $this->reportResult($triggerId, 'failed', -1, '', $e->getMessage());
        }
    }

    /**
     * Determine whether a command string is permitted to execute, based on
     * the configured allowlist. An empty (or missing) allowlist rejects
     * every command — this is a fail-closed default.
     */
    private function isCommandAllowed(string $command): bool
    {
        $allowlist = config('crontinel.agent.allowed_commands', []);

        if (! is_array($allowlist) || $allowlist === []) {
            return false;
        }

        foreach ($allowlist as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if ($command === $pattern || fnmatch($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    private function reportResult(string $triggerId, string $status, int $exitCode, string $output, ?string $error): void
    {
        try {
            $response = $this->http()->post($this->saasUrl(self::RESULT_PATH), array_filter([
                'trigger_id' => $triggerId,
                'status' => $status,
                'exit_code' => $exitCode,
                // Keep the end of the output, where errors usually are.
                'output' => mb_substr($output, -self::MAX_OUTPUT),
                'error_message' => $error === null ? null : mb_substr($error, 0, 1000),
            ], fn ($value) => $value !== null));

            if (! $response->successful()) {
                Log::warning('Crontinel Agent: failed to report command result', [
                    'trigger_id' => $triggerId,
                    'status_code' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Crontinel Agent: exception reporting command result', [
                'trigger_id' => $triggerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendHeartbeat(): void
    {
        $this->lastHeartbeatAt = microtime(true);

        $response = $this->http()->post($this->saasUrl(self::HEARTBEAT_PATH), [
            'agent_id' => $this->agentId(),
        ]);

        // The hosted app forgot this agent; register again on the next loop.
        if ($response->status() === 404) {
            $this->appId = null;
        } elseif (! $response->successful()) {
            Log::warning('Crontinel Agent: heartbeat failed', ['status_code' => $response->status()]);
        }
    }

    private function isHeartbeatDue(): bool
    {
        return (microtime(true) - $this->lastHeartbeatAt) >= (int) config('crontinel.agent.heartbeat_interval', 60);
    }

    /** Sleep in one-second steps so a stop signal is handled promptly. */
    private function pause(int $seconds): void
    {
        for ($i = 0; $i < $seconds && $this->running; $i++) {
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    private function installSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            $this->writeOutput('pcntl extension not available — signal-based graceful shutdown disabled.');

            return;
        }

        $stop = function (): void {
            $this->writeOutput('Received stop signal. Shutting down gracefully...');
            $this->running = false;
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);

        if (defined('SIGQUIT')) {
            pcntl_signal(SIGQUIT, $stop);
        }
    }

    public function isConfigured(): bool
    {
        return ! empty(config('crontinel.saas_key'));
    }

    private function agentId(): string
    {
        return gethostname() ?: 'laravel-agent';
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->apiKey())->acceptJson()->timeout(15);
    }

    private function writeOutput(string $message): void
    {
        $formatted = '['.date('Y-m-d H:i:s')."] Crontinel Agent: {$message}";

        if ($this->outputWriter !== null) {
            ($this->outputWriter)($formatted);
        } else {
            echo $formatted.PHP_EOL;
        }
    }

    private function apiKey(): string
    {
        return config('crontinel.saas_key', '');
    }

    private function saasUrl(string $path): string
    {
        $base = rtrim(config('crontinel.saas_url', 'https://app.crontinel.com'), '/');
        $base = preg_replace('#/api/?$#', '', $base);

        return $base.'/api'.$path;
    }
}
