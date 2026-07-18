<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation\Hooks;

use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;

final class ExternalCallHook
{
    public function __construct(
        private readonly TraceCollector $collector,
    ) {}

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function trace(string $label, callable $callback, array $meta = []): mixed
    {
        return $this->collector->traceExternal($label, $callback, $meta);
    }
}
