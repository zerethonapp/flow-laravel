<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Http\Request;

final class ControllerHook
{
    public function __construct(
        private readonly TraceCollector $collector,
    ) {}

    public function start(Request $request): void
    {
        $this->collector->startController($request);
    }

    public function finish(): void
    {
        $this->collector->finishController();
    }
}
