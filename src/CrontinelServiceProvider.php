<?php

declare(strict_types=1);

namespace Crontinel;

use Crontinel\Commands\CheckCommand;
use Crontinel\Commands\InstallCommand;
use Crontinel\Listeners\RecordScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\Event;
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

        $this->registerEventListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/crontinel.php' => config_path('crontinel.php'),
            ], 'crontinel-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'crontinel-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/crontinel'),
            ], 'crontinel-views');
        }
    }

    private function registerEventListeners(): void
    {
        if (! config('crontinel.cron.enabled', true)) {
            return;
        }

        $listener = RecordScheduledTaskRun::class;

        Event::listen(ScheduledTaskFinished::class, [$listener, 'handleFinished']);
        Event::listen(ScheduledTaskFailed::class, [$listener, 'handleFailed']);
    }
}
