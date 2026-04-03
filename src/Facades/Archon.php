<?php

declare(strict_types=1);

namespace ArchonLaravel\Facades;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Support\Facades\Facade;

final class Archon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TraceCollector::class;
    }
}
