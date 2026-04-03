<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Support;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;

/**
 * Trait for adding tracing capabilities to any class
 *
 * Usage:
 * class UserService {
 *     use Traceable;
 *
 *     public function findUser(int $id) {
 *         return $this->traceService('UserService.findUser', fn() => {
 *             // ... business logic
 *         });
 *     }
 * }
 */
trait Traceable
{
    /**
     * Trace a service operation
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    protected function traceService(string $label, callable $callback, array $meta = []): mixed
    {
        return app(TraceCollector::class)->traceService($label, $callback, $meta);
    }

    /**
     * Trace an external call
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    protected function traceExternal(string $label, callable $callback, array $meta = []): mixed
    {
        return app(TraceCollector::class)->traceExternal($label, $callback, $meta);
    }

    /**
     * Generic trace method
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    protected function trace(string $type, string $label, callable $callback, array $meta = []): mixed
    {
        return app(TraceCollector::class)->trace($type, $label, $callback, $meta);
    }
}
