<?php

declare(strict_types=1);

namespace Crontinel\Services;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleCatalog
{
    public function tasks(): array
    {
        return collect(app(Schedule::class)->events())
            ->reject(fn ($event) => $this->isPackageCommand($event))
            ->map(fn ($event) => $this->task($event))
            ->filter()
            ->values()
            ->all();
    }

    private function isPackageCommand(mixed $event): bool
    {
        $command = (string) ($event->command ?? '');

        return str_contains($command, 'crontinel:report') || str_contains($command, 'crontinel:schedule');
    }

    private function task(mixed $event): ?array
    {
        $command = $event->command ?? $event->description ?? null;
        if (! is_string($command) || trim($command) === '') {
            return null;
        }
        $task = ['command' => trim($command)];
        $description = $event->description ?? null;
        if (is_string($description) && trim($description) !== '') {
            $task['job_name'] = trim($description);
        }
        $expression = $event->expression ?? null;
        if (is_string($expression) && trim($expression) !== '') {
            $task['expression'] = trim($expression);
        }
        $environments = $event->environments ?? [];
        if (is_array($environments) && count($environments) === 1 && is_string($environments[0]) && trim($environments[0]) !== '') {
            $task['environment'] = trim($environments[0]);
        }

        return $task;
    }
}
