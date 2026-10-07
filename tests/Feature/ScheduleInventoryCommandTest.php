<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

it('names the laravel schedule and asks for the count that means the run counted', function () {
    config(['crontinel.saas_key' => 'test-key', 'crontinel.saas_url' => 'https://app.crontinel.com']);
    Http::fake(['*' => Http::response(['ok' => true, 'created' => ['billing-sync']], 200)]);

    app(Schedule::class)->command('billing:sync')->dailyAt('03:00')->environments('production')->name('billing-sync');
    app(Schedule::class)->command('crontinel:report')->everyMinute();

    $this->artisan('crontinel:schedule', ['--minimum' => '1'])
        ->expectsOutputToContain('billing-sync')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->data();
        $task = $body['tasks'][0] ?? [];

        return str_ends_with($request->url(), '/api/v1/ingest/schedule')
            && ($body['source'] ?? null) === 'laravel_schedule'
            && ($task['job_name'] ?? null) === 'billing-sync'
            && str_contains((string) ($task['command'] ?? ''), 'billing:sync')
            && ($task['expression'] ?? null) === '0 3 * * *'
            && ($task['environment'] ?? null) === 'production'
            && ($task['counted_minimum'] ?? null) === 1
            && collect($body['tasks'])->every(fn ($item) => ! str_contains((string) ($item['command'] ?? ''), 'crontinel:report'));
    });
});

it('prints the schedule and does not pretend to send it without an api key', function () {
    config(['crontinel.saas_key' => null]);
    Http::fake();
    app(Schedule::class)->call(function () {
        // import
    })->daily()->name('nightly-import');

    $this->artisan('crontinel:schedule')
        ->expectsQuestion('What number means the run counted? This is the processed_records minimum. Leave empty to skip.', '')
        ->expectsOutputToContain('nightly-import')
        ->expectsOutputToContain('No count was given')
        ->assertFailed();

    Http::assertNothingSent();
});
