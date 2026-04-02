<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;

final class ExternalCallHook
{
    public function __construct(
        private readonly InstrumentationManager $manager,
    ) {}

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function trace(string $label, callable $callback, array $meta = []): mixed
    {
        return $this->manager->traceExternal($label, $callback, $meta);
    }
}
