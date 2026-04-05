<?php

declare(strict_types=1);

namespace CronSentinel\Http\Controllers;

use CronSentinel\Monitors\HorizonMonitor;
use CronSentinel\Monitors\QueueMonitor;
use CronSentinel\Monitors\CronMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
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
        return view('cron-sentinel::dashboard', [
            'horizon' => config('cron-sentinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues'  => config('cron-sentinel.queues.enabled') ? $this->queue->all() : [],
            'crons'   => config('cron-sentinel.cron.enabled') ? $this->cron->all() : [],
        ]);
    }

    public function apiStatus(): JsonResponse
    {
        return response()->json([
            'horizon' => config('cron-sentinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues'  => config('cron-sentinel.queues.enabled') ? $this->queue->all() : [],
            'crons'   => config('cron-sentinel.cron.enabled') ? $this->cron->all() : [],
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
