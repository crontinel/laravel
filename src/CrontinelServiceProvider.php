<?php

declare(strict_types=1);

namespace Crontinel;

use Crontinel\Commands\InstallCommand;
use Crontinel\Commands\CheckCommand;
use Illuminate\Support\ServiceProvider;

class CrontinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crontinel.php', 'crontinel');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crontinel');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/crontinel.php' => config_path('crontinel.php'),
            ], 'cron-sentinel-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cron-sentinel-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/crontinel'),
            ], 'cron-sentinel-views');
        }
    }
}
