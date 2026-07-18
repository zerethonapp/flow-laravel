<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Middleware;

use Zerethon\Flow\Laravel\Instrumentation\Hooks\ControllerHook;
use Zerethon\Flow\Laravel\Instrumentation\Hooks\RequestHook;
use Zerethon\Flow\Laravel\Instrumentation\TraceWriter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CaptureFlowTrace
{
    public function __construct(
        private readonly RequestHook $requestHook,
        private readonly ControllerHook $controllerHook,
        private readonly TraceWriter $traceWriter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Check if ArchonFlow is enabled and if request should be traced
        if (!$this->shouldTrace($request)) {
            /** @var Response $response */
            $response = $next($request);
            return $response;
        }

        $this->requestHook->start($request);

        if ($this->shouldCollect('controller')) {
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
            if ($this->shouldCollect('controller')) {
                $this->controllerHook->finish();
            }

            $record = $this->requestHook->finish($response, $exception);
            if ($record !== null) {
                $this->traceWriter->write($record);

                if ($response !== null) {
                    $response->headers->set('X-Flow-Trace-Id', (string) $record['traceId']);
                }
            }
        }
    }

    /**
     * Check if the current request should be traced
     */
    private function shouldTrace(Request $request): bool
    {
        // Check if ArchonFlow is enabled
        $enabled = config('archonflow.enabled');
        if ($enabled === false) {
            return false;
        }

        // Check sample rate
        $sampleRate = (float) config('archonflow.sample_rate', 1.0);
        if ($sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) > $sampleRate) {
            return false;
        }

        // Check if request matches any excluded patterns
        $except = config('archonflow.except', []);
        $requestPath = $request->path();

        foreach ($except as $pattern) {
            if (str($requestPath)->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if we should collect data from the specified source
     */
    private function shouldCollect(string $source): bool
    {
        return (bool) config("archonflow.sources.{$source}", false);
    }
}
