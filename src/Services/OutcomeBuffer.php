<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Illuminate\Support\Str;

class OutcomeBuffer
{
    /** Matches the hosted ingest cap on a receipt's output. */
    public const OUTPUT_LIMIT = 10000;

    /** @var list<array{key: string, metrics: array<string, int|float>, timestamps: array<string, string>, output?: string}> */
    private array $stack = [];

    public function open(string $key): void
    {
        if (! Str::isUuid($key)) {
            return;
        }
        $this->stack[] = ['key' => $key, 'metrics' => [], 'timestamps' => []];
    }

    public function metric(string $name, mixed $value): void
    {
        try {
            if (! self::validName($name) || ! self::validNumber($value)) {
                return;
            }
            if ($this->stack !== []) {
                $index = array_key_last($this->stack);
                $this->stack[$index]['metrics'][$name] = $value;

                return;
            }
            $this->writeDetached('metrics', $name, $value);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }

    public function timestamp(string $name, string $value): void
    {
        try {
            if (! self::validName($name) || ! self::validTimestamp($value)) {
                return;
            }
            if ($this->stack !== []) {
                $index = array_key_last($this->stack);
                $this->stack[$index]['timestamps'][$name] = $value;

                return;
            }
            $this->writeDetached('timestamps', $name, $value);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }

    public function output(string $text): void
    {
        try {
            $text = self::clip($text);
            if ($this->stack !== []) {
                $this->stack[array_key_last($this->stack)]['output'] = $text;

                return;
            }
            $this->writeDetached('output', null, $text);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }

    /** Valid UTF-8, at most OUTPUT_LIMIT characters: the head, or the tail when $tail is true. */
    public static function clip(string $text, bool $tail = false): string
    {
        $text = mb_scrub($text, 'UTF-8');
        if (mb_strlen($text, 'UTF-8') <= self::OUTPUT_LIMIT) {
            return $text;
        }

        return $tail ? mb_substr($text, -self::OUTPUT_LIMIT, null, 'UTF-8') : mb_substr($text, 0, self::OUTPUT_LIMIT, 'UTF-8');
    }

    /** @return array{metrics?: array<string, int|float>, timestamps?: array<string, string>, output?: string}|null */
    public function close(string $key): ?array
    {
        try {
            $frame = null;
            foreach ($this->stack as $index => $candidate) {
                if ($candidate['key'] === $key) {
                    $frame = $candidate;
                    unset($this->stack[$index]);
                    $this->stack = array_values($this->stack);
                    break;
                }
            }
            $stored = $this->readDetached($key);
            $clean = self::normalize([
                'metrics' => array_merge($stored['metrics'] ?? [], $frame['metrics'] ?? []),
                'timestamps' => array_merge($stored['timestamps'] ?? [], $frame['timestamps'] ?? []),
            ]) ?? [];
            $output = $frame['output'] ?? $stored['output'] ?? null;
            if (is_string($output)) {
                $clean['output'] = self::clip($output);
            }

            return $clean === [] ? null : $clean;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{metrics?: array<string, int|float>, timestamps?: array<string, string>}|null */
    public static function normalize(mixed $outcomes): ?array
    {
        if (! is_array($outcomes)) {
            return null;
        }
        $metrics = [];
        foreach ($outcomes['metrics'] ?? [] as $name => $value) {
            if (is_string($name) && self::validName($name) && self::validNumber($value)) {
                $metrics[$name] = $value;
            }
        }
        $timestamps = [];
        foreach ($outcomes['timestamps'] ?? [] as $name => $value) {
            if (is_string($name) && self::validName($name) && is_string($value) && self::validTimestamp($value)) {
                $timestamps[$name] = $value;
            }
        }
        $clean = [];
        if ($metrics !== []) {
            $clean['metrics'] = $metrics;
        }
        if ($timestamps !== []) {
            $clean['timestamps'] = $timestamps;
        }

        return $clean === [] ? null : $clean;
    }

    private function writeDetached(string $bucket, ?string $name, int|float|string $value): void
    {
        $key = $this->detachedKey();
        if ($key === null) {
            return;
        }
        $current = $this->readDetached($key) ?? [];
        if ($name === null) {
            $current[$bucket] = $value;
        } else {
            $current[$bucket][$name] = $value;
        }
        $path = $this->path($key);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return;
        }
        file_put_contents($path, json_encode($current, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    /** @return array{metrics?: array<string, int|float>, timestamps?: array<string, string>}|null */
    private function readDetached(string $key): ?array
    {
        if (! Str::isUuid($key)) {
            return null;
        }
        $path = $this->path($key);
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        return is_array($decoded) ? $decoded : null;
    }

    private function detachedKey(): ?string
    {
        $value = getenv(BackgroundRunContext::VARIABLE);
        if (! is_string($value) || $value === '') {
            return null;
        }
        $decoded = base64_decode($value, true);
        $run = $decoded === false ? null : json_decode($decoded, true);
        $key = is_array($run) ? ($run['key'] ?? null) : null;

        return is_string($key) && Str::isUuid($key) ? $key : null;
    }

    private function path(string $key): string
    {
        return sys_get_temp_dir().'/crontinel-outcomes/'.$key.'.json';
    }

    private static function validName(string $name): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name) === 1;
    }

    private static function validNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_float($value) && is_finite($value);
    }

    private static function validTimestamp(string $value): bool
    {
        if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return false;
        }
        try {
            new \DateTimeImmutable($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
