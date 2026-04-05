<?php

declare(strict_types=1);

namespace Crontinel\Http\Controllers;

use Crontinel\Monitors\HorizonMonitor;
use Crontinel\Monitors\QueueMonitor;
use Crontinel\Monitors\CronMonitor;
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
        return view('crontinel::dashboard', [
            'horizon' => config('crontinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues'  => config('crontinel.queues.enabled') ? $this->queue->all() : [],
            'crons'   => config('crontinel.cron.enabled') ? $this->cron->all() : [],
        ]);
    }

    public function apiStatus(): JsonResponse
    {
        return response()->json([
            'horizon' => config('crontinel.horizon.enabled') ? $this->horizon->status() : null,
            'queues'  => config('crontinel.queues.enabled') ? $this->queue->all() : [],
            'crons'   => config('crontinel.cron.enabled') ? $this->cron->all() : [],
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
