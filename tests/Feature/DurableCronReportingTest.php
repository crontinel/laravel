<?php

declare(strict_types=1);

use Crontinel\Services\SaasReporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->spool = sys_get_temp_dir().'/crontinel-spool-'.bin2hex(random_bytes(12));
    config(['crontinel.saas_key' => 'private-test-key', 'crontinel.saas_url' => 'https://example.test/api/',
        'crontinel.reporting.durable' => true, 'crontinel.reporting.spool_path' => $this->spool]);
    Http::preventStrayRequests();
});

afterEach(function () {
    File::deleteDirectory($this->spool);
});

function durableRun(string $key = 'run-key'): void
{
    app(SaasReporter::class)->reportCronRun('reports:daily', 0, 1000, null,
        now()->subSecond()->toIso8601String(), now()->toIso8601String(), $key);
}

it('persists without network and replays original identity and times after a lost acknowledgement', function () {
    durableRun();
    Http::assertNothingSent();
    $path = glob($this->spool.'/*.json')[0];
    $original = json_decode(file_get_contents($path), true);
    expect(file_get_contents($path))->not->toContain('private-test-key');
    expect(fileperms($path) & 0777)->toBe(0600);
    expect(fileperms($this->spool) & 0777)->toBe(0700);
    Http::fakeSequence()->pushFailedConnection()->push(['ok' => true]);
    $first = (new SaasReporter)->flushCronReports();
    expect($first)->toMatchArray(['sent' => 0, 'retried' => 1, 'pending' => 1]);
    (new SaasReporter)->flushCronReports();
    Http::assertSentCount(1);
    $this->travel(61)->seconds();
    expect((new SaasReporter)->flushCronReports())->toMatchArray(['sent' => 1, 'pending' => 0]);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->data() === $original['payload']
        && $request->url() === 'https://example.test/api/v1/ingest/cron');
});

it('never reroutes retained reports after an app key or host change', function () {
    durableRun();
    Http::fake(['*' => Http::response([], 200)]);
    config(['crontinel.saas_key' => 'another-app-key']);
    expect(app(SaasReporter::class)->flushCronReports())->toMatchArray(['sent' => 0, 'pending' => 1]);
    config(['crontinel.saas_key' => 'private-test-key', 'crontinel.saas_url' => 'https://another.test']);
    app(SaasReporter::class)->flushCronReports();
    Http::assertNothingSent();
    config(['crontinel.saas_url' => 'https://example.test']);
    expect(app(SaasReporter::class)->flushCronReports())->toMatchArray(['sent' => 1, 'pending' => 0]);
});

it('retries server failures throttles and temporary authorization errors but discards invalid payloads', function (int $status, bool $retry) {
    durableRun();
    Http::fake(['*' => Http::response([], $status)]);
    expect(app(SaasReporter::class)->flushCronReports())->toMatchArray([
        'sent' => 0, 'retried' => (int) $retry, 'discarded' => (int) ! $retry, 'pending' => (int) $retry,
    ]);
})->with([[503, true], [429, true], [408, true], [401, true], [403, true], [422, false], [409, false], [302, false]]);

it('expires stale reports and corrupt records without sending them', function () {
    durableRun();
    file_put_contents($this->spool.'/corrupt.json', '{not json');
    $this->travel(25)->hours();
    expect(app(SaasReporter::class)->flushCronReports())->toMatchArray(['discarded' => 2, 'pending' => 0]);
    Http::assertNothingSent();
});

it('bounds attempts across drainer restarts', function () {
    durableRun();
    Http::fake(['*' => Http::response([], 503)]);
    for ($i = 0; $i < 12; $i++) {
        expect((new SaasReporter)->flushCronReports()['retried'])->toBe(1);
        $this->travel(61)->minutes();
    }
    expect((new SaasReporter)->flushCronReports())->toMatchArray(['discarded' => 1, 'pending' => 0]);
    Http::assertSentCount(12);
});

it('limits each drain and permits enqueue while a delivery is in progress', function () {
    foreach (range(1, 21) as $i) {
        durableRun('run-'.$i);
    }
    $first = true;
    Http::fake(function () use (&$first) {
        if ($first) {
            $first = false;
            expect((new SaasReporter)->flushCronReports()['busy'])->toBeTrue();
            durableRun('concurrent-enqueue');
        }

        return Http::response([], 200);
    });
    expect(app(SaasReporter::class)->flushCronReports())->toMatchArray(['sent' => 20, 'pending' => 2]);
    Http::assertSentCount(20);
});

it('keeps customer work running when storage and logging fail', function () {
    mkdir($this->spool, 0700);
    file_put_contents($this->spool.'/not-a-directory', '');
    config(['crontinel.reporting.spool_path' => $this->spool.'/not-a-directory/child']);
    Log::shouldReceive('warning')->andThrow(new RuntimeException('Broken logger'));
    durableRun();
    expect(app(SaasReporter::class)->flushCronReports()['failed'])->toBeTrue();
    Http::assertNothingSent();
});

it('bounds disk records and payload size without evicting pending evidence', function () {
    durableRun('original');
    $record = file_get_contents(glob($this->spool.'/*.json')[0]);
    for ($i = 1; $i < 1000; $i++) {
        file_put_contents($this->spool.'/fixture-'.$i.'.json', $record);
    }
    durableRun('overflow');
    expect(glob($this->spool.'/*.json'))->toHaveCount(1000);
    File::deleteDirectory($this->spool);
    app(SaasReporter::class)->reportCronRun('task', 1, 1, str_repeat('x', 65536), now()->toIso8601String(), now()->toIso8601String());
    expect(glob($this->spool.'/*.json'))->toBeEmpty();
    Http::assertNothingSent();
});

it('persists concurrent producer processes for a subsequent drainer', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Process acceptance requires pcntl');
    }
    $children = [];
    for ($i = 0; $i < 4; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Cannot fork producer');
        }
        if ($pid === 0) {
            foreach (range(1, 10) as $j) {
                durableRun('child-'.$i.'-'.$j);
            }
            exit(0);
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    expect(glob($this->spool.'/*.json'))->toHaveCount(40);
    $keys = [];
    Http::fake(function ($request) use (&$keys) {
        $keys[] = $request['request_key'];

        return Http::response([], 200);
    });
    (new SaasReporter)->flushCronReports();
    expect((new SaasReporter)->flushCronReports())->toMatchArray(['sent' => 20, 'pending' => 0]);
    expect(array_unique($keys))->toHaveCount(40);
});

it('exposes bounded delivery counts through the manual command', function () {
    durableRun();
    Http::fake(['*' => Http::response([], 200)]);
    $this->artisan('crontinel:flush-reports')->expectsOutputToContain('"sent":1')->assertSuccessful();
    config(['crontinel.reporting.durable' => false]);
    $this->artisan('crontinel:flush-reports')->assertFailed();
});
