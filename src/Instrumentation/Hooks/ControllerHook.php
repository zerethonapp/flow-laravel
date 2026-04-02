<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;
use Illuminate\Http\Request;

final class ControllerHook
{
    public function __construct(
        private readonly InstrumentationManager $manager,
    ) {}

    public function start(Request $request): void
    {
        $this->manager->startController($request);
    }

    public function finish(): void
    {
        $this->manager->finishController();
    }
}
