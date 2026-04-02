<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Middleware;

use ArchonFlow\Laravel\Instrumentation\Hooks\ControllerHook;
use ArchonFlow\Laravel\Instrumentation\Hooks\RequestHook;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureArchonTrace
{
    public function __construct(
        private readonly RequestHook $requestHook,
        private readonly ControllerHook $controllerHook,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->requestHook->start($request);
        $this->controllerHook->start($request);

        $response = null;
        $exception = null;

        try {
            /** @var Response $response */
            $response = $next($request);
            return $response;
        } catch (Throwable $throwable) {
            $exception = $throwable;
            throw $throwable;
        } finally {
            $this->controllerHook->finish();
            $this->requestHook->finish($response, $exception);
        }
    }
}
