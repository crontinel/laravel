<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Crontinel\Data\CronStatus;
use Crontinel\Data\HorizonStatus;
use Crontinel\Data\QueueStatus;
use Crontinel\Mail\AlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    private const DEDUP_TTL_SECONDS = 300; // 5 min — don't re-fire same alert

    public function evaluateHorizon(HorizonStatus $status): void
    {
        if (! $status->isHealthy()) {
            $this->fire(
                key: 'horizon',
                title: 'Horizon is unhealthy',
                message: $this->buildHorizonMessage($status),
                level: 'critical',
            );
        } else {
            $this->resolve('horizon');
        }
    }

    public function evaluateQueue(QueueStatus $status): void
    {
        $key = "queue:{$status->queue}";

        if (! $status->isHealthy()) {
            $this->fire(
                key: $key,
                title: "Queue [{$status->queue}] alert",
                message: $this->buildQueueMessage($status),
                level: 'warning',
            );
        } else {
            $this->resolve($key);
        }
    }

    public function evaluateCron(CronStatus $status): void
    {
        $key = "cron:{$status->command}";

        if (! $status->isHealthy()) {
            $this->fire(
                key: $key,
                title: "Cron [{$status->command}] {$status->status}",
                message: $this->buildCronMessage($status),
                level: $status->status === 'failed' ? 'critical' : 'warning',
            );
        } else {
            $this->resolve($key);
        }
    }

    private function fire(string $key, string $title, string $message, string $level): void
    {
        $cacheKey = "crontinel_alert_{$key}";

        // Deduplicate: don't fire the same alert again if already open
        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, ['title' => $title, 'fired_at' => now()->toIso8601String()], self::DEDUP_TTL_SECONDS);

        $channel = config('crontinel.alerts.channel');

        match ($channel) {
            'slack' => $this->sendSlack($title, $message, $level),
            'mail' => $this->sendMail($title, $message, $level),
            'webhook' => $this->sendWebhook($title, $message, $level, false),
            default => null,
        };
    }

    private function resolve(string $key): void
    {
        $cacheKey = "crontinel_alert_{$key}";

        if (! Cache::has($cacheKey)) {
            return;
        }

        $original = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        $channel = config('crontinel.alerts.channel');

        match ($channel) {
            'slack' => $this->sendSlack(
                title: "✅ Resolved: {$original['title']}",
                message: "Issue resolved. Originally fired at {$original['fired_at']}.",
                level: 'resolved',
            ),
            'mail' => $this->sendMail(
                title: "✅ Resolved: {$original['title']}",
                message: "Issue resolved. Originally fired at {$original['fired_at']}.",
                level: 'resolved',
            ),
            'webhook' => $this->sendWebhook(
                title: $original['title'],
                message: "Issue resolved. Originally fired at {$original['fired_at']}.",
                level: 'resolved',
                resolved: true,
            ),
            default => null,
        };
    }

    private function sendSlack(string $title, string $message, string $level): void
    {
        $webhookUrl = config('crontinel.alerts.slack.webhook_url');

        if (! $webhookUrl) {
            Log::warning('Crontinel: Slack alert channel configured but no webhook URL set.');

            return;
        }

        $emoji = match ($level) {
            'critical' => '🔴',
            'warning' => '⚠️',
            'resolved' => '✅',
            default => 'ℹ️',
        };

        try {
            Http::post($webhookUrl, [
                'text' => "{$emoji} *{$title}*\n{$message}",
            ]);
        } catch (\Throwable $e) {
            Log::error('Crontinel: Failed to send Slack alert.', ['error' => $e->getMessage()]);
        }
    }

    private function sendMail(string $title, string $message, string $level): void
    {
        $to = config('crontinel.alerts.mail.to');

        if (! $to) {
            Log::warning('Crontinel: Mail alert channel configured but no recipient set.');

            return;
        }

        try {
            Mail::to($to)->send(new AlertMail($title, $message));
        } catch (\Throwable $e) {
            Log::error('Crontinel: Failed to send email alert.', ['error' => $e->getMessage()]);
        }
    }

    private function sendWebhook(string $title, string $message, string $level, bool $resolved): void
    {
        $url = config('crontinel.alerts.webhook.url');

        if (! $url) {
            Log::warning('Crontinel: Webhook alert channel configured but no URL set.');

            return;
        }

        $headers = config('crontinel.alerts.webhook.headers', []);

        try {
            Http::withHeaders($headers)
                ->timeout(10)
                ->post($url, [
                    'title' => $title,
                    'message' => $message,
                    'level' => $level,
                    'resolved' => $resolved,
                    'fired_at' => now()->toIso8601String(),
                    'source' => 'crontinel',
                ]);
        } catch (\Throwable $e) {
            Log::error('Crontinel: Failed to send webhook alert.', ['error' => $e->getMessage()]);
        }
    }

    private function buildHorizonMessage(HorizonStatus $status): string
    {
        $lines = [];

        if (! $status->running) {
            $lines[] = '• Horizon is not running';
        }

        if ($status->pausedAt !== null) {
            $lines[] = '• Horizon is paused (since '.$status->pausedAt->diffForHumans().')';
        }

        $threshold = $status->failedJobsPerMinuteThreshold;
        if ($status->failedJobsPerMinute >= $threshold) {
            $lines[] = "• Failed jobs/min: {$status->failedJobsPerMinute} (threshold: {$threshold})";
        }

        foreach ($status->supervisors as $supervisor) {
            if (($supervisor['status'] ?? '') !== 'running') {
                $lines[] = "• Supervisor [{$supervisor['name']}] is {$supervisor['status']}";
            }
        }

        return implode("\n", $lines) ?: 'Horizon health check failed.';
    }

    private function buildQueueMessage(QueueStatus $status): string
    {
        $lines = [];

        if ($status->depth > $status->depthThreshold) {
            $lines[] = "• Queue depth: {$status->depth} jobs (threshold: {$status->depthThreshold})";
        }

        if ($status->oldestJobAgeSeconds !== null && $status->oldestJobAgeSeconds > $status->waitTimeThresholdSeconds) {
            $lines[] = "• Oldest job waiting: {$status->oldestJobAgeSeconds}s (threshold: {$status->waitTimeThresholdSeconds}s)";
        }

        if ($status->failedCount > 0) {
            $lines[] = "• Failed jobs: {$status->failedCount}";
        }

        return implode("\n", $lines) ?: "Queue [{$status->queue}] is unhealthy.";
    }

    private function buildCronMessage(CronStatus $status): string
    {
        return match ($status->status) {
            'failed' => "Command [{$status->command}] exited with code {$status->lastExitCode}.",
            'late' => "Command [{$status->command}] has not run on schedule (expression: {$status->expression}).",
            'never_run' => "Command [{$status->command}] has never run.",
            default => "Command [{$status->command}] is unhealthy.",
        };
    }
}
