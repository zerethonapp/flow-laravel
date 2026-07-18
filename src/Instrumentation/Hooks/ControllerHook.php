<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation\Hooks;

use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;
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
