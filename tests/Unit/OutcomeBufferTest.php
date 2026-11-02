<?php

declare(strict_types=1);

use Crontinel\Services\BackgroundRunContext;
use Crontinel\Services\OutcomeBuffer;
use Illuminate\Support\Str;

it('keeps a zero count for the run that is open', function () {
    $buffer = new OutcomeBuffer;
    $key = (string) Str::uuid();
    $buffer->open($key);
    $buffer->metric('processed_records', 0);

    expect($buffer->close($key))->toBe([
        'metrics' => ['processed_records' => 0],
    ]);
    expect($buffer->close($key))->toBeNull();
});

it('attaches a count to the innermost open run', function () {
    $buffer = new OutcomeBuffer;
    $outer = (string) Str::uuid();
    $inner = (string) Str::uuid();
    $buffer->open($outer);
    $buffer->open($inner);
    $buffer->metric('processed_records', 2);

    expect($buffer->close($inner)['metrics']['processed_records'])->toBe(2);
    expect($buffer->close($outer))->toBeNull();
});

it('ignores an invalid count without throwing', function () {
    $buffer = new OutcomeBuffer;
    $key = (string) Str::uuid();
    $buffer->open($key);
    $buffer->metric('processed_records', NAN);
    $buffer->metric('', 1);
    $buffer->timestamp('latest_artifact', '2026-10-06 12:00:00');

    expect($buffer->close($key))->toBeNull();
});

it('reads a background count from the schedule context file', function () {
    $key = (string) Str::uuid();
    $encoded = base64_encode(json_encode([
        'key' => $key,
        'start' => '2026-10-06T12:00:00+00:00',
        'start_ms' => 1,
        'task' => 'task',
    ], JSON_THROW_ON_ERROR));
    putenv(BackgroundRunContext::VARIABLE.'='.$encoded);
    try {
        $buffer = new OutcomeBuffer;
        $buffer->metric('processed_records', 0);
        $buffer->timestamp('latest_artifact', '2026-10-06T11:00:00Z');

        expect($buffer->close($key))->toBe([
            'metrics' => ['processed_records' => 0],
            'timestamps' => ['latest_artifact' => '2026-10-06T11:00:00Z'],
        ]);
    } finally {
        putenv(BackgroundRunContext::VARIABLE);
    }
});

it('keeps explicit output for the open run, truncated to the first 10,000 characters', function () {
    $buffer = new OutcomeBuffer;
    $key = (string) Str::uuid();
    $buffer->open($key);
    $buffer->output(str_repeat('é', 10_005));

    $output = $buffer->close($key)['output'];
    expect(mb_strlen($output))->toBe(10_000)
        ->and(mb_check_encoding($output, 'UTF-8'))->toBeTrue();
});

it('keeps the tail when clipping captured output and repairs a split character', function () {
    $clipped = OutcomeBuffer::clip(substr('é', 1).str_repeat('a', 10_000).'end', true);

    expect(mb_strlen($clipped))->toBe(10_000)
        ->and($clipped)->toEndWith('end')
        ->and(mb_check_encoding(OutcomeBuffer::clip(substr('é', 1).'ok'), 'UTF-8'))->toBeTrue();
});

it('reads background output from the schedule context file', function () {
    $key = (string) Str::uuid();
    putenv(BackgroundRunContext::VARIABLE.'='.base64_encode(json_encode(['key' => $key], JSON_THROW_ON_ERROR)));
    try {
        (new OutcomeBuffer)->output('42 invoices sent');

        expect((new OutcomeBuffer)->close($key))->toBe(['output' => '42 invoices sent']);
    } finally {
        putenv(BackgroundRunContext::VARIABLE);
    }
});

it('keeps background output in app storage, readable by the owner only', function () {
    $key = (string) Str::uuid();
    putenv(BackgroundRunContext::VARIABLE.'='.base64_encode(json_encode(['key' => $key], JSON_THROW_ON_ERROR)));
    try {
        (new OutcomeBuffer)->output('secret report');
        $path = storage_path('framework/crontinel-outcomes/'.$key.'.json');

        expect(is_file($path))->toBeTrue()
            ->and(fileperms($path) & 0777)->toBe(0600)
            ->and(is_file(sys_get_temp_dir().'/crontinel-outcomes/'.$key.'.json'))->toBeFalse();

        (new OutcomeBuffer)->close($key);
    } finally {
        putenv(BackgroundRunContext::VARIABLE);
    }
});
