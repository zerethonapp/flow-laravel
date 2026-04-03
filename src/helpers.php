<?php

declare(strict_types=1);

use ArchonFlow\Laravel\Instrumentation\TraceCollector;

if (!function_exists('archon')) {
    function archon(): TraceCollector
    {
        return app(TraceCollector::class);
    }
}
