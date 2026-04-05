<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    | The URI path where the Cron Sentinel dashboard is accessible.
    */
    'path' => env('CRON_SENTINEL_PATH', 'crontinel'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Horizon Integration
    |--------------------------------------------------------------------------
    | Set to false if you are not using Laravel Horizon.
    */
    'horizon' => [
        'enabled' => env('CRON_SENTINEL_HORIZON', true),
        // Alert when any supervisor has been paused or missing for N seconds
        'supervisor_alert_after_seconds' => 60,
        // Alert when failed jobs per minute exceeds this threshold
        'failed_jobs_per_minute_threshold' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'enabled' => true,
        // Queues to monitor. Empty array = monitor all queues.
        'watch' => [],
        // Alert when queue depth exceeds this number of pending jobs
        'depth_alert_threshold' => 1000,
        // Alert when a job has been waiting longer than N seconds
        'wait_time_alert_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron / Scheduled Command Monitoring
    |--------------------------------------------------------------------------
    */
    'cron' => [
        'enabled' => true,
        // Alert when a scheduled command misses its expected run by N seconds
        'late_alert_after_seconds' => 120,
        // How many days of cron history to retain
        'retain_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Channels
    |--------------------------------------------------------------------------
    | Supported: "mail", "slack", "null"
    */
    'alerts' => [
        'channel' => env('CRON_SENTINEL_ALERT_CHANNEL', 'null'),
        'mail' => [
            'to' => env('CRON_SENTINEL_ALERT_EMAIL'),
        ],
        'slack' => [
            'webhook_url' => env('CRON_SENTINEL_SLACK_WEBHOOK'),
        ],
    ],
];
