<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CronReportSpool
{
    private const MAX_BYTES = 65536;

    private const MAX_ENTRIES = 1000;

    private const RETENTION_SECONDS = 86400;

    /** Persist before any network request. Never store a credential in the spool. */
    public function enqueue(array $payload, string $url, string $key): void
    {
        $lock = null;
        try {
            $directory = $this->directory();
            $lock = $this->lock($directory.'/enqueue.lock', true);
            if ($lock === null) {
                throw new RuntimeException('Spool busy');
            }
            if (count(glob($directory.'/*.json') ?: []) >= self::MAX_ENTRIES) {
                throw new RuntimeException('Spool full');
            }
            $record = ['version' => 1, 'destination' => $this->destination($url, $key),
                'created_at' => now()->timestamp, 'attempts' => 0, 'next_attempt_at' => 0, 'payload' => $payload];
            $name = sprintf('%020.0f', microtime(true) * 1000000).'-'.bin2hex(random_bytes(16));
            $this->write($directory.'/'.$name.'.json', $record);
        } catch (\Throwable) {
            $this->warn('enqueue_failed');
        } finally {
            $this->unlock($lock);
        }
    }

    /** One local drainer at a time; enqueue never waits for HTTP delivery. */
    public function flush(string $url, string $key): array
    {
        $result = ['sent' => 0, 'retried' => 0, 'discarded' => 0, 'pending' => 0, 'busy' => false, 'failed' => false];
        $lock = null;
        try {
            $directory = $this->directory();
            $lock = $this->lock($directory.'/flush.lock', false);
            if ($lock === null) {
                $result['busy'] = true;

                return $result;
            }
            $deadline = hrtime(true) + 20_000_000_000;
            $attempted = 0;
            foreach (glob($directory.'/*.json') ?: [] as $path) {
                if (hrtime(true) >= $deadline || $attempted >= 20) {
                    break;
                }
                try {
                    if (filesize($path) > self::MAX_BYTES) {
                        throw new RuntimeException('Oversized record');
                    }
                    $record = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
                    if (! is_array($record) || ($record['version'] ?? null) !== 1
                        || ! is_int($record['created_at'] ?? null) || ! is_int($record['attempts'] ?? null)
                        || ! is_int($record['next_attempt_at'] ?? null) || ! is_string($record['destination'] ?? null)
                        || ! is_array($record['payload'] ?? null)) {
                        throw new RuntimeException('Invalid record');
                    }
                } catch (\Throwable) {
                    $this->discard($path, 'invalid_record', $result);

                    continue;
                }
                if ($record['created_at'] <= now()->timestamp - self::RETENTION_SECONDS || $record['attempts'] >= 12) {
                    $this->discard($path, 'expired_or_exhausted', $result);

                    continue;
                }
                // Changing an app key or host must never redirect retained telemetry.
                if (! hash_equals($record['destination'], $this->destination($url, $key))
                    || $record['next_attempt_at'] > now()->timestamp) {
                    continue;
                }
                $attempted++;
                // Persist the attempt before I/O so process crashes also consume the retry bound.
                $record['attempts']++;
                $record['next_attempt_at'] = now()->timestamp + min(3600, 60 * (2 ** ($record['attempts'] - 1)));
                $this->write($path, $record);
                $remaining = max(0.1, min(3, ($deadline - hrtime(true)) / 1_000_000_000));
                try {
                    $response = Http::withToken($key)->connectTimeout(min(1, $remaining))->timeout($remaining)
                        ->withOptions(['allow_redirects' => false])->post($url, $record['payload']);
                    if ($response->successful()) {
                        $this->remove($path);
                        $result['sent']++;

                        continue;
                    }
                    if (! $response->serverError() && ! in_array($response->status(), [401, 403, 408, 429], true)) {
                        $this->discard($path, 'rejected', $result);

                        continue;
                    }
                } catch (\Throwable) {
                    // A lost acknowledgement is replayed with the original request key.
                }
                $result['retried']++;
            }
            // An interrupted atomic write leaves only an unpublished temporary file.
            foreach (glob($directory.'/*.tmp') ?: [] as $path) {
                if (filemtime($path) < time() - 3600) {
                    $this->remove($path);
                }
            }
            $result['pending'] = count(glob($directory.'/*.json') ?: []);
            if ($result['retried'] > 0) {
                $this->warn('delivery_deferred');
            }
        } catch (\Throwable) {
            $result['failed'] = true;
            $this->warn('flush_failed');
        } finally {
            $this->unlock($lock);
        }

        return $result;
    }

    private function directory(): string
    {
        $path = (string) config('crontinel.reporting.spool_path', storage_path('app/crontinel-spool'));
        if ($path === '' || (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path))) {
            throw new RuntimeException('Spool unavailable');
        }
        if (! chmod($path, 0700)) {
            throw new RuntimeException('Cannot protect spool');
        }

        return $path;
    }

    private function destination(string $url, string $key): string
    {
        return hash('sha256', $url."\0".$key);
    }

    private function write(string $path, array $record): void
    {
        $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($json) > self::MAX_BYTES) {
            throw new RuntimeException('Report exceeds spool limit');
        }
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $file = fopen($temporary, 'xb');
        if ($file === false) {
            throw new RuntimeException('Cannot write spool');
        }
        try {
            if (! chmod($temporary, 0600) || fwrite($file, $json) !== strlen($json) || ! fflush($file) || ! fsync($file)) {
                throw new RuntimeException('Incomplete spool write');
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Cannot publish spool record');
            }
        } finally {
            fclose($file);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function lock(string $path, bool $wait): mixed
    {
        $file = fopen($path, 'c');
        if ($file === false) {
            throw new RuntimeException('Cannot lock spool');
        }
        chmod($path, 0600);
        $deadline = hrtime(true) + ($wait ? 250_000_000 : 0);
        do {
            if (flock($file, LOCK_EX | LOCK_NB)) {
                return $file;
            }
            if (! $wait || hrtime(true) >= $deadline) {
                fclose($file);

                return null;
            }
            usleep(1000);
        } while (true);
    }

    private function unlock(mixed $file): void
    {
        if (is_resource($file)) {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function remove(string $path): void
    {
        if (! unlink($path)) {
            throw new RuntimeException('Cannot remove spool record');
        }
    }

    private function discard(string $path, string $reason, array &$result): void
    {
        $this->remove($path);
        $result['discarded']++;
        $this->warn($reason);
    }

    private function warn(string $reason): void
    {
        try {
            Log::warning('Crontinel: cron report spool', ['reason' => $reason]);
        } catch (\Throwable) {
            // Reporting must not fail the customer's task, even with a broken logger.
        }
    }
}
