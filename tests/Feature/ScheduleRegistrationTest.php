<?php

declare(strict_types=1);

use Crontinel\Services\ScheduleCatalog;
use Illuminate\Console\Scheduling\Schedule;

function scheduledCommands(): array
{
    app()->forgetInstance(Schedule::class);

    return collect(app(Schedule::class)->events())->pluck('command')->map(fn ($c) => (string) $c)->all();
}

it('schedules crontinel:check when a local alert channel is set', function () {
    config(['crontinel.alerts.channel' => 'slack', 'crontinel.saas_key' => null]);

    expect(implode(' ', scheduledCommands()))
        ->toContain('crontinel:check --format=json')
        ->not->toContain('crontinel:report');
});

it('does not schedule crontinel:check without an alert channel', function () {
    config(['crontinel.alerts.channel' => null, 'crontinel.saas_key' => 'key']);

    expect(implode(' ', scheduledCommands()))
        ->toContain('crontinel:report')
        ->not->toContain('crontinel:check');
});

it('leaves crontinel:check out of the job list', function () {
    expect(ScheduleCatalog::isOwnCommand("'/usr/bin/php' 'artisan' crontinel:check --format=json"))->toBeTrue();
});
