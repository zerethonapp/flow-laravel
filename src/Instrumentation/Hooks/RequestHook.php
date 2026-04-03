<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestHook
{
    public function __construct(
        private readonly TraceCollector $collector,
    ) {}

    public function start(Request $request): void
    {
        $this->collector->startRequest($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function finish(?Response $response, ?Throwable $exception = null): ?array
    {
        return $this->collector->finishRequest($response, $exception);
    }
}
