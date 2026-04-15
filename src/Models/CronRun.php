<?php

declare(strict_types=1);

namespace Crontinel\Models;

use Illuminate\Database\Eloquent\Model;

class CronRun extends Model
{
    protected $table = 'crontinel_runs';

    protected $fillable = [
        'command',
        'ran_at',
        'exit_code',
        'duration_ms',
        'output',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'exit_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    public static function latestFor(string $command): ?static
    {
        return static::where('command', $command)
            ->latest('ran_at')
            ->first();
    }

    public static function record(string $command, int $exitCode, int $durationMs, ?string $output = null): static
    {
        return static::create([
            'command' => $command,
            'ran_at' => now(),
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'output' => $output,
        ]);
    }
}
