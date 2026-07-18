<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Providers;

use Zerethon\Flow\Laravel\Console\CacheServicesCommand;
use Zerethon\Flow\Laravel\Console\ClearServicesCacheCommand;
use Zerethon\Flow\Laravel\Instrumentation\Hooks\DatabaseHook;
use Zerethon\Flow\Laravel\Instrumentation\Hooks\ExternalHttpHook;
use Zerethon\Flow\Laravel\Instrumentation\ServiceAutoTraceRegistrar;
use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;
use Zerethon\Flow\Laravel\Instrumentation\TraceReader;
use Zerethon\Flow\Laravel\Instrumentation\TraceWriter;
use Zerethon\Flow\Laravel\Middleware\CaptureFlowTrace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class FlowServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/flow.php', 'flow');

        $this->app->singleton(TraceCollector::class, static fn (): TraceCollector => new TraceCollector());

        $this->app->singleton(TraceWriter::class, static function (): TraceWriter {
            $path = (string) config('flow.storage_path', base_path('.zerethon/flow-history.json'));
            $traceDirectory = (string) config('flow.trace_directory', storage_path('flow-traces'));
            $max = (int) config('flow.max_records', 1000);
            return new TraceWriter($path, $traceDirectory, $max);
        });

        $this->app->singleton(DatabaseHook::class);

        $this->app->singleton(TraceReader::class, static function (): TraceReader {
            $path = (string) config('flow.storage_path', base_path('.zerethon/flow-history.json'));
            return new TraceReader($path);
        });

        // Available regardless of except_environments — flow:cache-services
        // is precisely the tool you run in a production deploy pipeline to
        // pre-compute the manifest ServiceAutoTraceRegistrar reads at boot.
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheServicesCommand::class,
                ClearServicesCacheCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../../config/flow.php' => config_path('flow.php')],
                'flow-config',
            );
        }

        // Early return if ArchonFlow cannot be enabled
        if (!static::canBeEnabled()) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('flow.trace', CaptureFlowTrace::class);

        // Lets external tools (e.g. the flow-audit crawler) correlate a
        // request they just made — via the X-Flow-Trace-Id response
        // header set in CaptureFlowTrace — with the trace it produced,
        // over plain HTTP. Deliberately not attached to the 'web'/'api'
        // middleware groups, so it's never traced itself and skips
        // session/CSRF overhead.
        $router->get('/_flow/trace/{traceId}', function (string $traceId) {
            $record = app(TraceReader::class)->find($traceId);

            if ($record === null) {
                return response()->json(['error' => 'trace not found'], 404);
            }

            return response()->json($record);
        });

        // Lets a caller browse everything this site has captured — not just
        // a trace it already knows the ID of (e.g. a dashboard "Traces" tab
        // for a project, independent of any specific audit scan run).
        $router->get('/_flow/traces', function (\Illuminate\Http\Request $request) {
            $limit = max(1, min(200, (int) $request->query('limit', 50)));
            $records = app(TraceReader::class)->all($limit);

            $summaries = array_map(static function (array $record): array {
                $requestNode = null;
                foreach (($record['flow']['nodes'] ?? []) as $node) {
                    if (is_array($node) && ($node['type'] ?? null) === 'request') {
                        $requestNode = $node;
                        break;
                    }
                }
                $meta = is_array($requestNode['meta'] ?? null) ? $requestNode['meta'] : [];

                return [
                    'traceId' => $record['traceId'] ?? null,
                    'timestamp' => $record['timestamp'] ?? null,
                    'method' => $meta['method'] ?? null,
                    'uri' => $meta['uri'] ?? null,
                    'route' => $meta['route'] ?? null,
                    'totalTime' => $record['result']['totalTime'] ?? null,
                    'nodeCount' => $record['result']['nodeCount'] ?? null,
                    'status' => $record['result']['status'] ?? null,
                ];
            }, $records);

            return response()->json(['traces' => $summaries]);
        });

        // Auto-register middleware globally for zero-config experience
        if ($this->shouldCollect('request')) {
            $router->pushMiddlewareToGroup('web', CaptureFlowTrace::class);
            $router->pushMiddlewareToGroup('api', CaptureFlowTrace::class);
        }

        // Register database hook if enabled
        if ($this->shouldCollect('database')) {
            $options = config('flow.options.database', []);
            app(DatabaseHook::class)->register($options['capture_sql'] ?? false);
        }

        // Auto-detect outbound HTTP calls (Http:: facade) — mirrors Debugbar's
        // HttpClientCollector, no manual Flow::trace('external', ...) needed.
        if ($this->shouldCollect('external')) {
            app(ExternalHttpHook::class)->register($this->app->make(Dispatcher::class));
        }

        // Auto-wrap classes under configured namespaces in a tracing proxy so
        // 'service' spans are captured without touching the class itself.
        if ($this->shouldCollect('service')) {
            $namespaces = (array) config('flow.trace_namespaces', []);
            if ($namespaces !== []) {
                $cachePath = (string) config('flow.services_cache_path', base_path('.zerethon/services-cache.php'));
                $excludePrefixes = (array) config('flow.trace_namespace_excludes', []);
                (new ServiceAutoTraceRegistrar())->register($this->app, $namespaces, $cachePath, $excludePrefixes);
            }
        }
    }

    /**
     * Check if ArchonFlow can be enabled based on environment
     */
    public static function canBeEnabled(): bool
    {
        $app = app();
        $exceptEnvironments = config('flow.except_environments', ['production', 'testing']);

        return !$app->environment($exceptEnvironments);
    }

    /**
     * Check if we should collect data from the specified source
     */
    protected function shouldCollect(string $source): bool
    {
        // If the package is explicitly enabled in config, honor that
        $enabled = config('flow.enabled');
        if ($enabled !== null && !$enabled) {
            return false;
        }

        // Check if this specific source is enabled
        return (bool) config("flow.sources.{$source}", false);
    }
}
