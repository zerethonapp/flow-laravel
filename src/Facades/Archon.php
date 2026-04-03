<?php

declare(strict_types=1);

namespace ArchonLaravel\Facades;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed trace(string $type, string $label, callable $callback, array $meta = [])
 * @method static mixed traceService(string $label, callable $callback, array $meta = [])
 * @method static mixed traceExternal(string $label, callable $callback, array $meta = [])
 * @method static string|null currentTraceId()
 *
 * @see \ArchonFlow\Laravel\Instrumentation\TraceCollector
 */
final class Archon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TraceCollector::class;
    }
}
