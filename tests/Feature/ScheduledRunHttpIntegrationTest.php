<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

it('runs a real scheduled failure and recovery through the app and delivery worker', function () {
    $url = getenv('CRONTINEL_TEST_API_URL');
    if (! $url) {
        $this->markTestSkipped('Run through the workspace SDK integration harness.');
    }
    expect(parse_url($url, PHP_URL_HOST))->toBe('127.0.0.1');
    $key = getenv('CRONTINEL_TEST_API_KEY');
    $collector = getenv('CRONTINEL_TEST_COLLECTOR_URL');
    expect(parse_url($collector, PHP_URL_HOST))->toBe('127.0.0.1');
    config(['crontinel.saas_key' => $key, 'crontinel.saas_url' => $url]);
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    $this->artisan('migrate', ['--database' => 'testing']);
    Http::record();

    $file = tempnam(getenv('CRONTINEL_TEST_TEMP_DIR'), 'job-');
    try {
        file_put_contents($file, '1');
        $command = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('exit((int) file_get_contents('.var_export($file, true).'));');
        app(Schedule::class)->exec($command)->everyMinute();
        $headers = ['Authorization' => 'Bearer '.$key];

        $this->artisan('schedule:run')->assertExitCode(0);
        $runs = Http::withHeaders($headers)->get($url.'/api/v1/apps/sdk-fixture/cron-runs')->throw()->json('data');
        expect($runs)->toHaveCount(1)->and($runs[0]['status'])->toBe('failed');
        expect(Http::withHeaders($headers)->get($url.'/api/v1/apps/sdk-fixture/alerts')->throw()->json('data'))->toHaveCount(1);

        $waitForDelivery = function (int $count) use ($collector) {
            $deadline = microtime(true) + 15;
            do {
                $messages = Http::get($collector)->throw()->json();
                if (count($messages) >= $count) {
                    return $messages;
                }
                usleep(100000);
            } while (microtime(true) < $deadline);
            throw new RuntimeException('Delivery worker did not reach the local webhook collector.');
        };
        expect($waitForDelivery(1)[0]['state'])->toBe('firing');

        file_put_contents($file, '0');
        $this->artisan('schedule:run')->assertExitCode(0);
        $runs = Http::withHeaders($headers)->get($url.'/api/v1/apps/sdk-fixture/cron-runs')->throw()->json('data');
        expect($runs)->toHaveCount(2)->and(array_column($runs, 'status'))->toContain('completed', 'failed');
        expect(Http::withHeaders($headers)->get($url.'/api/v1/apps/sdk-fixture/alerts')->throw()->json('data'))->toBe([]);
        expect($waitForDelivery(2)[1]['state'])->toBe('resolved');

        $reports = Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/v1/ingest/cron'))->values();
        expect($reports)->toHaveCount(4);
        expect($reports[0][0]['request_key'])->toBe($reports[1][0]['request_key'])
            ->and($reports[2][0]['request_key'])->toBe($reports[3][0]['request_key'])
            ->and($reports[0][0]['request_key'])->not->toBe($reports[2][0]['request_key']);
        Http::withHeaders($headers)->post($url.'/api/v1/ingest/cron', $reports[3][0]->data())->throw();
        expect(Http::withHeaders($headers)->get($url.'/api/v1/apps/sdk-fixture/cron-runs')->throw()->json('data'))->toHaveCount(2);
    } finally {
        unlink($file);
    }
});
