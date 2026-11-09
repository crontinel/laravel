<?php

declare(strict_types=1);

namespace Crontinel\Http\Controllers;

use Crontinel\Data\CronStatus;
use Crontinel\Laravel\Monitors\CronMonitor;
use Crontinel\Laravel\Monitors\HorizonMonitor;
use Crontinel\Laravel\Monitors\QueueMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly HorizonMonitor $horizon,
        private readonly QueueMonitor $queue,
        private readonly CronMonitor $cron,
    ) {}

    public function __invoke(): View
    {
        Gate::authorize('crontinel.view');

        return view('crontinel::dashboard', [
            'horizon' => config('crontinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues' => config('crontinel.queues.enabled') ? $this->queue->all() : [],
            'crons' => config('crontinel.cron.enabled') ? array_map($this->withoutArguments(...), $this->cron->all()) : [],
        ]);
    }

    public function apiStatus(): JsonResponse
    {
        // Gate::authorize checks against the current user and throws 403 if denied
        Gate::authorize('crontinel.view');

        return response()->json([
            'horizon' => config('crontinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues' => config('crontinel.queues.enabled') ? $this->queue->all() : [],
            'crons' => config('crontinel.cron.enabled') ? array_map($this->withoutArguments(...), $this->cron->all()) : [],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /** Arguments can hold secrets, so web output names the command only. */
    private function withoutArguments(CronStatus $status): CronStatus
    {
        $name = preg_match('/artisan[\'"]?\s+([^\s\'"]+)/', $status->command, $match)
            ? $match[1]
            : (str_contains(trim($status->command), ' ') ? strtok(trim($status->command), ' ').' …' : $status->command);

        return new CronStatus(...['command' => $name] + get_object_vars($status));
    }
}
