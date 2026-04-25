<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Crontinel\Data\CronStatus;
use Crontinel\Monitors\CronMonitor;
use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaasReporter
{
    public function reportStatus(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            $horizon = app(HorizonMonitor::class)->status();
            $queues = app(QueueMonitor::class)->all();
            $crons = app(CronMonitor::class)->all();

            $overallStatus = $this->resolveOverallStatus($horizon, $queues, $crons);

            $payload = [
                'status' => $overallStatus,
                'framework_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'horizon' => config('crontinel.horizon.enabled', true) ? [
                    'enabled' => true,
                    'status' => $horizon->running ? 'running' : ($horizon->pausedAt ? 'paused' : 'stopped'),
                    'supervisors' => $horizon->supervisors,
                    'failed_per_minute' => $horizon->failedJobsPerMinute,
                    'paused_at' => $horizon->pausedAt?->toIso8601String(),
                ] : ['enabled' => false],
                'queues' => collect($queues)->map(fn ($q) => [
                    'connection' => $q->connection,
                    'queue' => $q->queue,
                    'depth' => $q->depth,
                    'failed_count' => $q->failedCount,
                    'oldest_job_age_seconds' => $q->oldestJobAgeSeconds,
                ])->all(),
                'crons' => collect($crons)->map(fn (CronStatus $c) => [
                    'command' => $c->command,
                    'status' => $c->status,
                    'last_run_at' => $c->lastRunAt?->toIso8601String(),
                ])->all(),
            ];

            Http::withToken($this->apiKey())
                ->timeout(10)
                ->post($this->saasUrl('/v1/ingest/ping'), $payload);
        } catch (\Throwable $e) {
            Log::warning('Crontinel: failed to report status to SaaS', ['error' => $e->getMessage()]);
        }
    }

    public function reportCronRun(
        string $command,
        int $exitCode,
        int $durationMs,
        ?string $output,
        string $startedAt,
        string $finishedAt,
    ): void {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            Http::withToken($this->apiKey())
                ->timeout(10)
                ->post($this->saasUrl('/v1/ingest/cron'), [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'duration_ms' => $durationMs,
                    'output' => $output,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'status' => $exitCode === 0 ? 'completed' : 'failed',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Crontinel: failed to report cron run to SaaS', ['error' => $e->getMessage()]);
        }
    }

    private function resolveOverallStatus(mixed $horizon, array $queues, array $crons): string
    {
        if (config('crontinel.horizon.enabled', true) && ! $horizon->isHealthy()) {
            return 'critical';
        }

        foreach ($crons as $cron) {
            if ($cron->status === 'failed') {
                return 'critical';
            }
        }

        foreach ($queues as $queue) {
            if (! $queue->isHealthy()) {
                return 'warning';
            }
        }

        foreach ($crons as $cron) {
            if ($cron->status === 'late') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    private function isConfigured(): bool
    {
        return ! empty(config('crontinel.saas_key'));
    }

    private function apiKey(): string
    {
        return config('crontinel.saas_key', '');
    }

    private function saasUrl(string $path): string
    {
        $base = rtrim(config('crontinel.saas_url', 'https://app.crontinel.com'), '/');

        // Normalize: strip trailing /api or /api/ so either
        // CRONTINEL_API_URL=https://app.crontinel.com or
        // CRONTINEL_API_URL=https://app.crontinel.com/api works correctly
        $base = preg_replace('#/api/?$#', '', $base);

        return $base.'/api'.$path;
    }
}
