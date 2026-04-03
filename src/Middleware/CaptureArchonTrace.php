<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Middleware;

use ArchonFlow\Laravel\Instrumentation\Hooks\ControllerHook;
use ArchonFlow\Laravel\Instrumentation\Hooks\RequestHook;
use ArchonFlow\Laravel\Instrumentation\TraceWriter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureArchonTrace
{
    public function __construct(
        private readonly RequestHook $requestHook,
        private readonly ControllerHook $controllerHook,
        private readonly TraceWriter $traceWriter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!(bool) config('archonflow.enabled', true)) {
            /** @var Response $response */
            $response = $next($request);
            return $response;
        }

        $this->requestHook->start($request);

        if ((bool) config('archonflow.capture_controller', true)) {
            $this->controllerHook->start($request);
        }

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
            if ((bool) config('archonflow.capture_controller', true)) {
                $this->controllerHook->finish();
            }

            $record = $this->requestHook->finish($response, $exception);
            if ($record !== null) {
                $this->traceWriter->write($record);
            }
        }
    }
}
