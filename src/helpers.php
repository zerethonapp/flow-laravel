<?php

declare(strict_types=1);

use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;

if (!function_exists("archon")) {
    function archon(): InstrumentationManager
    {
        return app(InstrumentationManager::class);
    }
}
