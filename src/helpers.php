<?php

declare(strict_types=1);

use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;

if (!function_exists('flow')) {
    function flow(): TraceCollector
    {
        return app(TraceCollector::class);
    }
}
