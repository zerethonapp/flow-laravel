<?php

declare(strict_types=1);

return [
    'enabled' => env('ARCHONFLOW_ENABLED', true),

    // Persist traces in the same default location used by archon-cli.
    'storage_path' => env('ARCHONFLOW_STORAGE_PATH', base_path('.archon/flow-history.json')),

    // Simple one-trace-per-file output for functional verification.
    'trace_directory' => env('ARCHONFLOW_TRACE_DIRECTORY', storage_path('archon-traces')),

    // Keep the file bounded to avoid unbounded growth.
    'max_records' => (int) env('ARCHONFLOW_MAX_RECORDS', 1000),

    // Capture a controller-scoped node around route execution.
    'capture_controller' => env('ARCHONFLOW_CAPTURE_CONTROLLER', true),

    // Include SQL text in metadata. Disable in production if sensitive.
    'capture_query_sql' => env('ARCHONFLOW_CAPTURE_QUERY_SQL', false),
];
