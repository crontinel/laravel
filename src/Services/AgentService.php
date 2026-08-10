<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Crontinel\Data\SseEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AgentService
{
    private const SSE_PATH = '/v1/agent/stream';

    private const COMMAND_RESULT_PATH = '/v1/agent/command/%s/result';

    private const HEARTBEAT_PATH = '/v1/agent/heartbeat';

    private const HEARTBEAT_INTERVAL = 60;

    private const MAX_RECONNECT_DELAY = 60;

    private const STREAM_SELECT_TIMEOUT = 1;

    /** Whether the agent should keep running. */
    private bool $running = true;

    /** Accumulated SSE buffer between stream reads. */
    private string $sseBuffer = '';

    /** Partial SSE event being built across multiple reads. */
    private ?SseEvent $currentEvent = null;

    /** Timestamp of the last heartbeat (microtime). */
    private float $lastHeartbeatAt = 0.0;

    /** When the agent started (microtime). */
    private float $startedAt = 0.0;

    /** Number of consecutive reconnects (for exponential backoff). */
    private int $reconnectAttempt = 0;

    /** Callable for writing output (bound by the command). */
    private ?\Closure $outputWriter = null;

    /**
     * Bind an output writer (typically from a Console\Command).
     */
    public function setOutputWriter(\Closure $writer): void
    {
        $this->outputWriter = $writer;
    }

    /**
     * Run the agent daemon indefinitely (blocking loop).
     */
    public function run(): void
    {
        $this->startedAt = microtime(true);
        $this->lastHeartbeatAt = $this->startedAt;

        $this->installSignalHandlers();

        $this->writeOutput('Agent starting...');

        while ($this->running) {
            try {
                $this->connectAndListen();
            } catch (\Throwable $e) {
                Log::error('Crontinel Agent: connection error', [
                    'error' => $e->getMessage(),
                ]);
                $this->writeOutput('Connection error: '.$e->getMessage());
            }

            if (! $this->running) {
                break;
            }

            $this->reconnectAttempt++;
            $delay = min(
                $this->reconnectAttempt > 1
                    ? (1 << min($this->reconnectAttempt - 1, 5))
                    : 1,
                self::MAX_RECONNECT_DELAY,
            );

            $this->writeOutput("Reconnecting in {$delay}s (attempt {$this->reconnectAttempt})...");
            sleep($delay);
        }

        $this->writeOutput('Agent stopped.');
    }

    /**
     * Open an SSE connection and listen for events.
     */
    private function connectAndListen(): void
    {
        $this->resetSseParser();
        $this->reconnectAttempt = 0;

        $url = $this->buildSseUrl();
        $this->writeOutput("Connecting to {$url}...");

        $fp = $this->openSseConnection($url);

        if ($fp === false) {
            return;
        }

        $this->writeOutput('Connected. Listening for commands...');

        if (! $this->readResponseHeaders($fp)) {
            fclose($fp);

            return;
        }

        $this->readSseStream($fp);

        fclose($fp);
        $this->writeOutput('Disconnected.');
    }

    /**
     * @return resource|false
     */
    private function openSseConnection(string $url)
    {
        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['host'])) {
            Log::error('Crontinel Agent: invalid SSE URL', ['url' => $url]);

            return false;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = ($parsed['path'] ?? '/').(isset($parsed['query']) ? '?'.$parsed['query'] : '');
        $transport = $scheme === 'https' ? 'ssl' : 'tcp';

        $remote = "{$transport}://{$host}:{$port}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if ($fp === false) {
            Log::error('Crontinel Agent: socket connect failed', [
                'error' => $errstr,
                'code' => $errno,
            ]);
            $this->writeOutput("Socket connect failed: {$errstr} ({$errno})");

            return false;
        }

        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$host}\r\n"
            ."Authorization: Bearer {$this->apiKey()}\r\n"
            ."Accept: text/event-stream\r\n"
            ."Cache-Control: no-cache\r\n"
            ."Connection: keep-alive\r\n"
            ."User-Agent: crontinel-agent/1.0\r\n"
            ."\r\n";

        fwrite($fp, $request);

        return $fp;
    }

    /**
     * @param  resource  $fp
     */
    private function readResponseHeaders($fp): bool
    {
        $statusLine = fgets($fp);
        if ($statusLine === false) {
            $this->writeOutput('Connection closed while reading response.');
            Log::warning('Crontinel Agent: connection closed during response headers.');

            return false;
        }

        if (! preg_match('#^HTTP/\d\.\d\s+2\d{2}#', $statusLine)) {
            $this->writeOutput('Unexpected response: '.trim($statusLine));
            Log::warning('Crontinel Agent: non-200 response', ['status_line' => $statusLine]);
            while (($line = fgets($fp)) !== false && rtrim($line) !== '') {
                // consume remaining headers
            }

            return false;
        }

        // Consume remaining headers until blank line
        while (($line = fgets($fp)) !== false && rtrim($line) !== '') {
            //
        }

        return true;
    }

    /**
     * @param  resource  $fp
     */
    private function readSseStream($fp): void
    {
        stream_set_blocking($fp, false);

        while ($this->running && ! feof($fp)) {
            $read = [$fp];
            $write = null;
            $except = null;

            $result = @stream_select($read, $write, $except, self::STREAM_SELECT_TIMEOUT);

            if ($result === false) {
                pcntl_signal_dispatch();

                continue;
            }

            if ($result === 0) {
                pcntl_signal_dispatch();
                if ($this->isHeartbeatDue()) {
                    $this->sendHeartbeat();
                }

                continue;
            }

            $chunk = fread($fp, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $this->feedSseParser($chunk);
            pcntl_signal_dispatch();

            if ($this->isHeartbeatDue()) {
                $this->sendHeartbeat();
            }
        }
    }

    /**
     * Feed raw data into the SSE parser.
     */
    public function feedSseParser(string $data): void
    {
        $this->sseBuffer .= $data;

        while (($pos = strpos($this->sseBuffer, "\n")) !== false) {
            $line = substr($this->sseBuffer, 0, $pos);
            $this->sseBuffer = substr($this->sseBuffer, $pos + 1);
            $line = rtrim($line, "\r");

            if ($line === '') {
                $this->dispatchCurrentEvent();
                $this->currentEvent = null;

                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            $value = ltrim($value);

            switch ($field) {
                case 'event':
                    if ($this->currentEvent === null) {
                        $this->currentEvent = new SseEvent;
                    }
                    $this->currentEvent->event = $value;
                    break;

                case 'data':
                    if ($this->currentEvent === null) {
                        $this->currentEvent = new SseEvent;
                    }
                    $this->currentEvent->data .= $value;
                    break;

                case 'id':
                    if ($this->currentEvent === null) {
                        $this->currentEvent = new SseEvent;
                    }
                    $this->currentEvent->id = $value;
                    break;
            }
        }
    }

    /**
     * Dispatch a complete SSE event.
     */
    private function dispatchCurrentEvent(): void
    {
        if ($this->currentEvent === null) {
            return;
        }

        $event = $this->currentEvent;

        if ($event->event === 'command' && $event->data !== '') {
            $this->handleCommandEvent($event->data);
        } elseif ($event->event === 'ping') {
            $this->writeOutput('Received server ping.');
        }
    }

    /**
     * Handle an incoming command event.
     */
    public function handleCommandEvent(string $jsonData): void
    {
        $payload = json_decode($jsonData, true);

        if (! is_array($payload) || ! isset($payload['command_id'], $payload['command'])) {
            Log::warning('Crontinel Agent: malformed command event', ['data' => $jsonData]);
            $this->writeOutput('Malformed command event received.');

            return;
        }

        $commandId = $payload['command_id'];
        $commandString = $payload['command'];
        $env = $payload['env'] ?? [];
        $timeout = $payload['timeout'] ?? 300;

        $this->writeOutput("Executing command [{$commandId}]: {$commandString}");

        $startedAt = now();
        $startMicro = microtime(true);

        $process = Process::fromShellCommandline(
            command: $commandString,
            env: $env,
            timeout: $timeout,
        );

        try {
            $process->run();
            $exitCode = $process->getExitCode();
            $output = $process->getOutput().$process->getErrorOutput();
            $finishedAt = now();
            $durationMs = (int) round((microtime(true) - $startMicro) * 1000);
            $status = $exitCode === 0 ? 'completed' : 'failed';

            $this->writeOutput("Command [{$commandId}] finished: {$status} ({$durationMs}ms, exit {$exitCode})");

            $this->reportCommandResult(
                commandId: $commandId,
                status: $status,
                exitCode: $exitCode,
                output: $output,
                startedAt: $startedAt->toIso8601String(),
                finishedAt: $finishedAt->toIso8601String(),
                durationMs: $durationMs,
            );
        } catch (\Throwable $e) {
            $finishedAt = now();
            $durationMs = (int) round((microtime(true) - $startMicro) * 1000);

            $this->writeOutput("Command [{$commandId}] error: {$e->getMessage()}");

            $this->reportCommandResult(
                commandId: $commandId,
                status: 'failed',
                exitCode: -1,
                output: $e->getMessage(),
                startedAt: $startedAt->toIso8601String(),
                finishedAt: $finishedAt->toIso8601String(),
                durationMs: $durationMs,
            );
        }
    }

    /**
     * Report a command result to the SaaS.
     */
    private function reportCommandResult(
        string $commandId,
        string $status,
        int $exitCode,
        string $output,
        string $startedAt,
        string $finishedAt,
        int $durationMs,
    ): void {
        try {
            $response = Http::withToken($this->apiKey())
                ->timeout(10)
                ->post($this->saasUrl(sprintf(self::COMMAND_RESULT_PATH, $commandId)), [
                    'command_id' => $commandId,
                    'status' => $status,
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'duration_ms' => $durationMs,
                ]);

            if (! $response->successful()) {
                Log::warning('Crontinel Agent: failed to report command result', [
                    'command_id' => $commandId,
                    'status_code' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Crontinel Agent: exception reporting command result', [
                'command_id' => $commandId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a heartbeat to the SaaS.
     */
    private function sendHeartbeat(): void
    {
        $this->lastHeartbeatAt = microtime(true);
        $uptime = (int) round(microtime(true) - $this->startedAt);

        try {
            $response = Http::withToken($this->apiKey())
                ->timeout(10)
                ->post($this->saasUrl(self::HEARTBEAT_PATH), [
                    'status' => 'connected',
                    'uptime_seconds' => $uptime,
                ]);

            if (! $response->successful()) {
                Log::warning('Crontinel Agent: heartbeat failed', [
                    'status_code' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Crontinel Agent: heartbeat exception', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isHeartbeatDue(): bool
    {
        return (microtime(true) - $this->lastHeartbeatAt) >= self::HEARTBEAT_INTERVAL;
    }

    private function installSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            $this->writeOutput('pcntl extension not available — signal-based graceful shutdown disabled.');

            return;
        }

        $self = $this; // PHP 8.2+ $this is allowed in closures, but being explicit for readability

        pcntl_signal(SIGTERM, function () use ($self): void {
            $self->writeOutput('Received SIGTERM. Shutting down gracefully...');
            $self->running = false;
        });

        pcntl_signal(SIGINT, function () use ($self): void {
            $self->writeOutput('Received SIGINT. Shutting down gracefully...');
            $self->running = false;
        });

        if (defined('SIGQUIT')) {
            pcntl_signal(SIGQUIT, function () use ($self): void {
                $self->writeOutput('Received SIGQUIT. Shutting down gracefully...');
                $self->running = false;
            });
        }
    }

    private function resetSseParser(): void
    {
        $this->sseBuffer = '';
        $this->currentEvent = null;
    }

    private function buildSseUrl(): string
    {
        return $this->saasUrl(self::SSE_PATH);
    }

    public function isConfigured(): bool
    {
        return ! empty(config('crontinel.saas_key'));
    }

    private function writeOutput(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] Crontinel Agent: {$message}";

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
