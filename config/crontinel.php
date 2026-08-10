<?php

declare(strict_types=1);
use Laravel\Horizon\Horizon;

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    | The URI path where the Crontinel dashboard is accessible.
    */
    'path' => env('CRONTINEL_PATH', 'crontinel'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | SaaS Reporting
    |--------------------------------------------------------------------------
    | Set CRONTINEL_API_KEY to connect this app to the hosted SaaS at
    | app.crontinel.com. When set, status is reported every minute and
    | cron run events are pushed after each scheduled task completes.
    */
    'saas_key' => env('CRONTINEL_API_KEY'),
    'saas_url' => env('CRONTINEL_API_URL', 'https://app.crontinel.com'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Integration
    |--------------------------------------------------------------------------
    | Set to false if you are not using Laravel Horizon.
    */
    'horizon' => [
        'enabled' => env('CRONTINEL_HORIZON', class_exists(Horizon::class)),
        'supervisor_alert_after_seconds' => 60,
        'failed_jobs_per_minute_threshold' => 5,
        'connection' => env('CRONTINEL_HORIZON_CONNECTION', 'horizon'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'enabled' => true,
        'watch' => [],
        'depth_alert_threshold' => 1000,
        'wait_time_alert_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron / Scheduled Command Monitoring
    |--------------------------------------------------------------------------
    */
    'cron' => [
        'enabled' => true,
        'late_alert_after_seconds' => 120,
        'retain_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Channels
    |--------------------------------------------------------------------------
    | Supported: "mail", "slack", "webhook", null
    | Set CRONTINEL_ALERT_CHANNEL to enable alerts.
    */
    'alerts' => [
        'channel' => env('CRONTINEL_ALERT_CHANNEL'),
        'mail' => [
            'to' => env('CRONTINEL_ALERT_EMAIL'),
        ],
        'slack' => [
            'webhook_url' => env('CRONTINEL_SLACK_WEBHOOK'),
        ],
        'webhook' => [
            'url' => env('CRONTINEL_WEBHOOK_URL'),
            'headers' => env('CRONTINEL_WEBHOOK_HEADERS'),
            'timeout' => env('CRONTINEL_WEBHOOK_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Daemon (php artisan crontinel:agent)
    |--------------------------------------------------------------------------
    | Settings for the remote command execution agent that connects to the
    | Crontinel SaaS via SSE to receive and execute commands.
    */
    'agent' => [
        'enabled' => env('CRONTINEL_AGENT_ENABLED', false),
        'heartbeat_interval' => 60,
        'max_reconnect_delay' => 60,
        'default_command_timeout' => 300,

        /*
        |----------------------------------------------------------------------
        | Allowed Commands
        |----------------------------------------------------------------------
        | Commands received from the Crontinel SaaS are only executed if they
        | match one of these patterns. Patterns are matched with fnmatch(),
        | so "*" is a wildcard and any other string must match exactly.
        |
        | IMPORTANT: the default is an empty array, which means NO commands
        | are permitted. This is a fail-closed default — you must explicitly
        | opt in to the exact commands (or patterns) you want the agent to be
        | able to run. An empty list is NOT "allow everything".
        */
        'allowed_commands' => [],
    ],
];
