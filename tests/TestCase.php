<?php

declare(strict_types=1);

namespace Crontinel\Tests;

use Crontinel\CrontinelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CrontinelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Strip auth middleware for tests — routes are registered at boot time
        // so this must be set here, not in individual test beforeEach blocks.
        $app['config']->set('crontinel.middleware', ['web']);
    }
}
