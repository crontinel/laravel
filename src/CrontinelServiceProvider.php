<?php

declare(strict_types=1);

namespace Crontinel;

use Crontinel\Commands\CheckCommand;
use Crontinel\Commands\InstallCommand;
use Crontinel\Commands\PruneCommand;
use Crontinel\Commands\ReportCommand;
use Crontinel\Listeners\RecordScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CheckCommand::class,
                ReportCommand::class,
                PruneCommand::class,
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

        Event::listen(ScheduledTaskStarting::class, [$listener, 'handleStarting']);
        Event::listen(ScheduledTaskFinished::class, [$listener, 'handleFinished']);
        Event::listen(ScheduledTaskFailed::class, [$listener, 'handleFailed']);
    }

    private function registerSchedule(): void
    {
        // Only register the reporter schedule when an API key is configured
        if (empty(config('crontinel.saas_key'))) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('crontinel:report')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }
}
