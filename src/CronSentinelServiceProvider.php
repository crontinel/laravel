<?php

declare(strict_types=1);

namespace CronSentinel;

use CronSentinel\Commands\InstallCommand;
use CronSentinel\Commands\CheckCommand;
use Illuminate\Support\ServiceProvider;

class CronSentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cron-sentinel.php', 'cron-sentinel');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cron-sentinel');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/cron-sentinel.php' => config_path('cron-sentinel.php'),
            ], 'cron-sentinel-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cron-sentinel-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/cron-sentinel'),
            ], 'cron-sentinel-views');
        }
    }
}
