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
        'service' => env('ARCHONFLOW_SOURCES_SERVICE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Traced Service Namespaces
    |--------------------------------------------------------------------------
    |
    | Classes under these namespaces are automatically wrapped in a timing
    | proxy the moment the container resolves them — no Traceable trait or
    | Archon::trace() call needed in the class itself. Only classes actually
    | resolved through the container (constructor injection, app()->make(),
    | or bound to an interface they implement) are covered; manually `new`'d
    | instances are not.
    |
    | Defaults to the whole app/ tree so this works regardless of how you
    | organize business logic — flat App\Services, DDD-style
    | App\Domain\*\Services, App\UseCases, App\Actions, etc. all get covered
    | without listing each folder. Narrow it if you'd rather opt in
    | explicitly; see trace_namespace_excludes below for opting parts out
    | instead.
    |
    | Map each namespace prefix to the directory that contains it.
    |
    */

    'trace_namespaces' => [
        'App' => app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Traced Service Exclusions
    |--------------------------------------------------------------------------
    |
    | Namespace prefixes to skip during the trace_namespaces scan. Defaults
    | cover Laravel scaffolding that either isn't business logic (Providers,
    | Console, Exceptions) or is already captured a different way
    | (Controllers become the 'controller' node). Eloquent models are always
    | skipped regardless of this list — see TracingProxyFactory — because
    | wrapping them in a proxy subclass breaks late static binding.
    |
    */

    'trace_namespace_excludes' => [
        'App\\Http\\Controllers',
        'App\\Http\\Middleware',
        'App\\Http\\Requests',
        'App\\Providers',
        'App\\Console',
        'App\\Exceptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Traced Service Cache
    |--------------------------------------------------------------------------
    |
    | The trace_namespaces scan is a filesystem walk + Reflection check per
    | class — real cost on every boot outside Octane. Run
    | `php artisan archon:cache-services` (e.g. in your deploy pipeline) to
    | pre-compute this list; when the file exists it's used instead of
    | scanning live. Run `archon:clear-services-cache` to remove it.
    |
    */

    'services_cache_path' => env('ARCHONFLOW_SERVICES_CACHE_PATH', base_path('.archon/services-cache.php')),

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
