<?php

declare(strict_types=1);

use Crontinel\Services\SaasReporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function makeReporter(): SaasReporter
{
    return new SaasReporter;
}

// ── URL construction ──────────────────────────────────────────────────────────

it('sends cron run to /v1/ingest/cron', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'reports:generate',
        exitCode: 0,
        durationMs: 150,
        output: null,
        startedAt: '2026-04-25T10:00:00Z',
        finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://app.crontinel.com/api/v1/ingest/cron'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request->method() === 'POST';
    });
});

it('sends completed status for zero exit code cron run', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'reports:generate',
        exitCode: 0,
        durationMs: 150,
        output: null,
        startedAt: '2026-04-25T10:00:00Z',
        finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return ($body['status'] ?? null) === 'completed'
            && ($body['command'] ?? null) === 'reports:generate'
            && ($body['exit_code'] ?? null) === 0;
    });
});

it('sends failed status for non-zero exit code cron run', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'reports:generate',
        exitCode: 1,
        durationMs: 150,
        output: 'Error: something went wrong',
        startedAt: '2026-04-25T10:00:00Z',
        finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return ($body['status'] ?? null) === 'failed'
            && ($body['exit_code'] ?? null) === 1;
    });
});

it('includes correct cron run fields in payload', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'php artisan inspire',
        exitCode: 0,
        durationMs: 42,
        output: 'Laravel ipsum',
        startedAt: '2026-04-25T10:00:00Z',
        finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return ($body['command'] ?? null) === 'php artisan inspire'
            && ($body['duration_ms'] ?? null) === 42
            && ($body['output'] ?? null) === 'Laravel ipsum'
            && ($body['started_at'] ?? null) === '2026-04-25T10:00:00Z'
            && ($body['finished_at'] ?? null) === '2026-04-25T10:00:01Z';
    });
});

// ── saas_url config ────────────────────────────────────────────────────────────

it('uses custom saas_url when configured', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://custom.example.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'test', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        return str_starts_with($request->url(), 'https://custom.example.com/api');
    });
});

it('uses default saas_url when not explicitly configured', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'test', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        return str_starts_with($request->url(), 'https://app.crontinel.com/api');
    });
});

it('strips trailing slash from saas_url to avoid double slash', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://custom.example.com/api/']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'test', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSent(function (Request $request) {
        // Should NOT have double slash: https://custom.example.com/api//v1/...
        return ! str_contains($request->url(), '//v1');
    });
});

// ── Graceful degradation ───────────────────────────────────────────────────────

it('does not send any request when saas_key is empty', function () {
    config(['crontinel.saas_key' => '']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);

    makeReporter()->reportCronRun(
        command: 'test', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertNothingSent();
});

it('does not throw when saas_key is null', function () {
    config(['crontinel.saas_key' => null]);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);

    makeReporter()->reportCronRun(
        command: 'test', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertNothingSent();
});

// ── Multiple reports ──────────────────────────────────────────────────────────

it('can send multiple reports in sequence', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    makeReporter()->reportCronRun(
        command: 'test1', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );
    makeReporter()->reportCronRun(
        command: 'test2', exitCode: 0, durationMs: 1,
        output: null, startedAt: '2026-04-25T10:00:00Z', finishedAt: '2026-04-25T10:00:01Z',
    );

    Http::assertSentCount(2);
});
