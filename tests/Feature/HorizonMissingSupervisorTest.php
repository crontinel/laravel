<?php

declare(strict_types=1);

use Crontinel\Data\HorizonStatus;
use Crontinel\Laravel\Monitors\HorizonMonitor;
use Illuminate\Support\Facades\Cache;

function horizonWith(array $names, bool $running = true): HorizonStatus
{
    return new HorizonStatus(
        running: $running,
        supervisors: array_map(fn (string $name) => ['name' => "host-1:{$name}", 'status' => 'running', 'processes' => 1, 'queue' => 'default'], $names),
        failedJobsPerMinute: 0.0,
        pausedAt: null,
        failedJobsPerMinuteThreshold: 5.0,
    );
}

beforeEach(function () {
    Cache::flush();
    config()->set('horizon.environments', ['testing' => ['supervisor-1' => [], 'supervisor-b' => []], 'other' => ['x' => []]]);
    config()->set('crontinel.horizon.supervisor_alert_after_seconds', 60);
    $this->travelTo(now());
});

it('reports a configured supervisor only after it has been missing past the grace period', function () {
    $monitor = app(HorizonMonitor::class);
    $status = horizonWith(['supervisor-1']);

    expect($monitor->missingSupervisors($status))->toBe([]);   // first sight: Horizon may be restarting it

    $this->travel(30)->seconds();
    expect($monitor->missingSupervisors($status))->toBe([]);

    $this->travel(31)->seconds();
    expect($monitor->missingSupervisors($status))->toBe(['supervisor-b']);

    expect($monitor->missingSupervisors(horizonWith(['supervisor-1', 'supervisor-b'])))->toBe([]);

    // A supervisor that comes back and goes away again starts a new grace period.
    expect($monitor->missingSupervisors($status))->toBe([]);
});

it('does not judge supervisors while the master is stopped, or without a plan for the environment', function () {
    $monitor = app(HorizonMonitor::class);

    $this->travel(120)->seconds();
    expect($monitor->missingSupervisors(horizonWith([], running: false)))->toBe([]);

    config()->set('horizon.environments', ['production' => ['supervisor-1' => []]]);
    expect($monitor->missingSupervisors(horizonWith([])))->toBe([]);
});

it('matches a wildcard environment', function () {
    config()->set('horizon.environments', ['test*' => ['supervisor-1' => []]]);
    $monitor = app(HorizonMonitor::class);
    $status = horizonWith([]);

    $monitor->missingSupervisors($status);
    $this->travel(61)->seconds();

    expect($monitor->missingSupervisors($status))->toBe(['supervisor-1']);
});
