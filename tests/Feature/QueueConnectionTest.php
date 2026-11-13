<?php

declare(strict_types=1);

use Crontinel\Laravel\Monitors\QueueMonitor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('reads database queue depth from the queue connection', function () {
    config()->set('database.connections.queue_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('queue.connections.separate', [
        'driver' => 'database',
        'connection' => 'queue_testing',
        'table' => 'custom_jobs',
        'queue' => 'default',
    ]);
    config()->set('queue.default', 'separate');

    Schema::connection('queue_testing')->create('custom_jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    DB::connection('queue_testing')->table('custom_jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => time(),
        'created_at' => time() - 120,
    ]);

    $monitor = app(QueueMonitor::class);
    $status = $monitor->statusFor('separate', 'default');
    $discovered = $monitor->all();

    expect($status->depth)->toBe(1)
        ->and($status->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(119)
        ->and($discovered)->toHaveCount(1)
        ->and($discovered[0]->connection)->toBe('separate')
        ->and($discovered[0]->depth)->toBe(1)
        ->and($discovered[0]->oldestJobAgeSeconds)->toBeGreaterThanOrEqual(119);
});

it('reports queues on other connections as connection:queue and counts their failures apart', function () {
    config()->set('database.connections.queue_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('queue.connections.separate', ['driver' => 'database', 'connection' => 'queue_testing', 'table' => 'custom_jobs', 'queue' => 'default']);
    config()->set('queue.default', 'sync');
    config()->set('crontinel.queues.connections', ['separate' => ['default'], 'missing' => ['x'], 'sync' => ['ignored']]);

    Schema::connection('queue_testing')->create('custom_jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    DB::connection('queue_testing')->table('custom_jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);

    Schema::create('failed_jobs', function (Blueprint $table) {
        $table->id();
        $table->string('connection');
        $table->string('queue');
    });
    DB::table('failed_jobs')->insert([
        ['connection' => 'separate', 'queue' => 'default'],
        ['connection' => 'separate', 'queue' => 'default'],
        ['connection' => 'sync', 'queue' => 'default'],
    ]);

    $byName = collect(app(QueueMonitor::class)->all())->keyBy('queue');

    expect($byName->keys()->all())->toBe(['default', 'separate:default'])
        ->and($byName['default']->connection)->toBe('sync')
        ->and($byName['default']->failedCount)->toBe(1)
        ->and($byName['separate:default']->connection)->toBe('separate')
        ->and($byName['separate:default']->depth)->toBe(1)
        ->and($byName['separate:default']->failedCount)->toBe(2);
});
