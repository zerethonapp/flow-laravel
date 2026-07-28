<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Providers;

use Zerethon\Flow\Laravel\Console\CacheServicesCommand;
use Zerethon\Flow\Laravel\Console\ClearServicesCacheCommand;
use Zerethon\Flow\Laravel\Console\InstallCommand;
use Zerethon\Flow\Laravel\Console\RoutesCommand;
use Zerethon\Flow\Laravel\Instrumentation\Hooks\DatabaseHook;
use Zerethon\Flow\Laravel\Instrumentation\Hooks\ExternalHttpHook;
use Zerethon\Flow\Laravel\Instrumentation\ServiceAutoTraceRegistrar;
use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;
use Zerethon\Flow\Laravel\Instrumentation\TraceWriter;
use Zerethon\Flow\Laravel\Middleware\CaptureFlowTrace;
use Zerethon\Flow\Laravel\Transport\HttpPushTransport;
use Zerethon\Flow\Laravel\Transport\NullTransport;
use Zerethon\Flow\Laravel\Transport\TraceTransport;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Throwable;

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

        $this->app->singleton(TraceTransport::class, static function (): TraceTransport {
            $server = (string) config('flow.connected.server', '');
            $projectId = (string) config('flow.connected.project_id', '');
            $secretKey = (string) config('flow.connected.secret_key', '');

            if ($server === '' || $projectId === '' || $secretKey === '') {
                return new NullTransport();
            }

            return new HttpPushTransport(
                $server,
                $projectId,
                $secretKey,
                (string) config('flow.connected.version', '1.0'),
                (string) config('flow.connected.environment', 'production'),
            );
        });

        // Available regardless of except_environments — flow:cache-services
        // is precisely the tool you run in a production deploy pipeline to
        // pre-compute the manifest ServiceAutoTraceRegistrar reads at boot.
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheServicesCommand::class,
                ClearServicesCacheCommand::class,
                InstallCommand::class,
                RoutesCommand::class,
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

        // Early return if Flow cannot be enabled
        if (!static::canBeEnabled()) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('flow.trace', CaptureFlowTrace::class);

        // Auto-register middleware globally for zero-config experience
        if ($this->shouldCollect('request')) {
            $router->pushMiddlewareToGroup('web', CaptureFlowTrace::class);
            $router->pushMiddlewareToGroup('api', CaptureFlowTrace::class);
            $this->registerExceptionCapture();
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
     * `Illuminate\Routing\Pipeline` (the pipeline the 'web'/'api' groups —
     * including CaptureFlowTrace — run through) catches any exception at
     * the exact pipe it's thrown from and renders it immediately, so
     * `$next($request)` back in CaptureFlowTrace::handle() never actually
     * throws — see TraceCollector::recordException()'s doc comment for the
     * full explanation, confirmed via a live reproduction, not inspection
     * alone. `reportable()` is Laravel's own public extension point for
     * exactly this: it fires from `Handler::report()`, which every
     * exception still passes through before being rendered.
     */
    private function registerExceptionCapture(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'reportable')) {
            return;
        }

        $handler->reportable(function (Throwable $e): void {
            $this->app->make(TraceCollector::class)->recordException($e);
        });
    }

    /**
     * Check if Flow can be enabled based on environment
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
