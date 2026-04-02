<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestHook
{
    public function __construct(
        private readonly InstrumentationManager $manager,
    ) {}

    public function start(Request $request): void
    {
        $this->manager->startRequest($request);
    }

    public function finish(?Response $response, ?Throwable $exception = null): void
    {
        $this->manager->finishRequest($response, $exception);
    }
}
