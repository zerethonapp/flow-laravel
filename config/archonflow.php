<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ArchonFlow Settings
    |--------------------------------------------------------------------------
    |
    | ArchonFlow is enabled by default in non-production environments.
    | You can override the value by setting ARCHONFLOW_ENABLED to true or false.
    |
    | You can provide an array of URI patterns that must be excluded (eg. 'api/health')
    |
    */

    'enabled' => env('ARCHONFLOW_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    |
    | Control what percentage of requests to trace (0.0 - 1.0)
    | 1.0 = trace all requests (default)
    | 0.1 = trace 10% of requests
    |
    */

    'sample_rate' => (float) env('ARCHONFLOW_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Environment Control
    |--------------------------------------------------------------------------
    |
    | Specify which environments ArchonFlow should NOT run in.
    | By default, it's disabled in production and testing.
    |
    */

    'except_environments' => ['production', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | URI patterns to exclude from tracing.
    |
    */

    'except' => [
        'telescope*',
        'horizon*',
        '_debugbar*',
        'livewire*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configure where ArchonFlow stores trace data.
    |
    */

    // Persist traces in the same default location used by archon-cli.
    'storage_path' => env('ARCHONFLOW_STORAGE_PATH', base_path('.archon/flow-history.json')),

    // Simple one-trace-per-file output for functional verification.
    'trace_directory' => env('ARCHONFLOW_TRACE_DIRECTORY', storage_path('archon-traces')),

    // Keep the file bounded to avoid unbounded growth.
    'max_records' => (int) env('ARCHONFLOW_MAX_RECORDS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Source Collectors
    |--------------------------------------------------------------------------
    |
    | Enable/disable individual trace sources
    |
    */

    'sources' => [
        'request' => env('ARCHONFLOW_SOURCES_REQUEST', true),
        'controller' => env('ARCHONFLOW_SOURCES_CONTROLLER', true),
        'database' => env('ARCHONFLOW_SOURCES_DATABASE', true),
        'external' => env('ARCHONFLOW_SOURCES_EXTERNAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Options
    |--------------------------------------------------------------------------
    |
    | Configure individual sources
    |
    */

    'options' => [
        'database' => [
            'capture_sql' => env('ARCHONFLOW_OPTIONS_DATABASE_CAPTURE_SQL', false),
            'capture_bindings' => env('ARCHONFLOW_OPTIONS_DATABASE_CAPTURE_BINDINGS', false),
        ],
    ],
];
