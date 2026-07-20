<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Flow Settings
    |--------------------------------------------------------------------------
    |
    | Flow is enabled by default in non-production environments.
    | You can override the value by setting FLOW_ENABLED to true or false.
    |
    | You can provide an array of URI patterns that must be excluded (eg. 'api/health')
    |
    */

    'enabled' => env('FLOW_ENABLED'),

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

    'sample_rate' => (float) env('FLOW_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Environment Control
    |--------------------------------------------------------------------------
    |
    | Specify which environments Flow should NOT run in.
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
    | Configure where Flow stores trace data.
    |
    */

    // Persist traces in the same default location used by flow-cli.
    'storage_path' => env('FLOW_STORAGE_PATH', base_path('.zerethon/flow-history.json')),

    // Simple one-trace-per-file output for functional verification.
    'trace_directory' => env('FLOW_TRACE_DIRECTORY', storage_path('flow-traces')),

    // Keep the file bounded to avoid unbounded growth.
    'max_records' => (int) env('FLOW_MAX_RECORDS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Source Collectors
    |--------------------------------------------------------------------------
    |
    | Enable/disable individual trace sources
    |
    */

    'sources' => [
        'request' => env('FLOW_SOURCES_REQUEST', true),
        'controller' => env('FLOW_SOURCES_CONTROLLER', true),
        'database' => env('FLOW_SOURCES_DATABASE', true),
        'external' => env('FLOW_SOURCES_EXTERNAL', true),
        'service' => env('FLOW_SOURCES_SERVICE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Traced Service Namespaces
    |--------------------------------------------------------------------------
    |
    | Classes under these namespaces are automatically wrapped in a timing
    | proxy the moment the container resolves them — no Traceable trait or
    | Flow::trace() call needed in the class itself. Only classes actually
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
    | `php artisan flow:cache-services` (e.g. in your deploy pipeline) to
    | pre-compute this list; when the file exists it's used instead of
    | scanning live. Run `flow:clear-services-cache` to remove it.
    |
    */

    'services_cache_path' => env('FLOW_SERVICES_CACHE_PATH', base_path('.zerethon/services-cache.php')),

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
            'capture_sql' => env('FLOW_OPTIONS_DATABASE_CAPTURE_SQL', false),
            'capture_bindings' => env('FLOW_OPTIONS_DATABASE_CAPTURE_BINDINGS', false),
        ],

        // On by default (unlike capture_sql) — this is a safety default,
        // not an opt-in feature. Masks sensitive-named query-string values
        // and route-parameter values (e.g. a password-reset {token}) in
        // captured request/external-call URLs, sensitive-keyed entries in
        // manually-supplied trace meta (Flow::traceService()/traceExternal()),
        // and email-shaped strings wherever they appear. Does not touch SQL
        // text (see capture_sql above) or non-email-shaped exception message
        // content — see src/Instrumentation/Masker.php's own doc comment for
        // exactly what is and isn't covered. Set to false only if you need
        // raw, unmasked values for debugging and understand the trade-off.
        'mask_sensitive_data' => env('FLOW_OPTIONS_MASK_SENSITIVE_DATA', true),
    ],
];
