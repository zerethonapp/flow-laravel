<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Helpers;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;

final class Archon
{
    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public static function trace(string $type, string $label, callable $callback, array $meta = []): mixed
    {
        if ($type === 'external') {
            return app(TraceCollector::class)->traceExternal($label, $callback, $meta);
        }

        return app(TraceCollector::class)->traceService($label, $callback, $meta);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public static function external(string $label, callable $callback, array $meta = []): mixed
    {
        return app(TraceCollector::class)->traceExternal($label, $callback, $meta);
    }

    public static function traceId(): ?string
    {
        return app(TraceCollector::class)->currentTraceId();
    }
}
