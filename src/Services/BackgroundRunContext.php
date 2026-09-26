<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;
use WeakMap;

class BackgroundRunContext
{
    public const VARIABLE = 'CRONTINEL_SCHEDULE_CONTEXT';

    private WeakMap $previous;

    public function __construct()
    {
        $this->previous = new WeakMap;
    }

    public function begin(Event $task): ?array
    {
        // sudo may strip the environment before both the command and schedule:finish.
        if ($task->user !== null) {
            return null;
        }
        $time = now();
        $run = ['key' => (string) Str::uuid(), 'start' => $time->toIso8601String(),
            'start_ms' => $time->getTimestampMs(), 'task' => $this->identity($task)];
        $value = base64_encode(json_encode($run, JSON_THROW_ON_ERROR));
        $this->previous[$task] = ['process' => getenv(self::VARIABLE),
            'env' => $_ENV[self::VARIABLE] ?? null, 'server' => $_SERVER[self::VARIABLE] ?? null];
        if (! putenv(self::VARIABLE.'='.$value)) {
            unset($this->previous[$task]);

            return null;
        }
        // Symfony Process reads these before falling back to getenv().
        $_ENV[self::VARIABLE] = $_SERVER[self::VARIABLE] = $value;

        return $run;
    }

    public function restore(Event $task): void
    {
        if (! isset($this->previous[$task])) {
            return;
        }
        $previous = $this->previous[$task];
        putenv($previous['process'] === false ? self::VARIABLE : self::VARIABLE.'='.$previous['process']);
        foreach (['env' => '_ENV', 'server' => '_SERVER'] as $key => $global) {
            if ($previous[$key] === null) {
                unset($GLOBALS[$global][self::VARIABLE]);
            } else {
                $GLOBALS[$global][self::VARIABLE] = $previous[$key];
            }
        }
        unset($this->previous[$task]);
    }

    public function recover(Event $task): ?array
    {
        $value = getenv(self::VARIABLE);
        if ($task->user !== null || ! is_string($value) || strlen($value) > 2048) {
            return null;
        }
        $decoded = base64_decode($value, true);
        $run = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($run) || ! is_string($run['key'] ?? null) || ! Str::isUuid($run['key'])
            || ! is_string($run['start'] ?? null) || ! is_int($run['start_ms'] ?? null)
            || $run['start_ms'] < 0 || ! is_string($run['task'] ?? null)
            || ! hash_equals($this->identity($task), $run['task'])) {
            return null;
        }
        try {
            if (abs(CarbonImmutable::parse($run['start'])->getTimestampMs() - $run['start_ms']) >= 1000) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return $run;
    }

    private function identity(Event $task): string
    {
        return hash('sha256', $task->mutexName()."\0".$task->command);
    }
}
