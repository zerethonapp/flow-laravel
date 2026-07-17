<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Providers;

use ArchonFlow\Laravel\Console\CacheServicesCommand;
use ArchonFlow\Laravel\Console\ClearServicesCacheCommand;
use ArchonFlow\Laravel\Instrumentation\Hooks\DatabaseHook;
use ArchonFlow\Laravel\Instrumentation\Hooks\ExternalHttpHook;
use ArchonFlow\Laravel\Instrumentation\ServiceAutoTraceRegistrar;
use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use ArchonFlow\Laravel\Instrumentation\TraceWriter;
use ArchonFlow\Laravel\Middleware\CaptureArchonTrace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class ArchonFlowServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/archonflow.php', 'archonflow');

        $this->app->singleton(TraceCollector::class, static fn (): TraceCollector => new TraceCollector());

        $this->app->singleton(TraceWriter::class, static function (): TraceWriter {
            $path = (string) config('archonflow.storage_path', base_path('.archon/flow-history.json'));
            $traceDirectory = (string) config('archonflow.trace_directory', storage_path('archon-traces'));
            $max = (int) config('archonflow.max_records', 1000);
            return new TraceWriter($path, $traceDirectory, $max);
        });

        $this->app->singleton(DatabaseHook::class);

        // Available regardless of except_environments — archon:cache-services
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
                [__DIR__ . '/../../config/archonflow.php' => config_path('archonflow.php')],
                'archonflow-config',
            );
        }

        // Early return if ArchonFlow cannot be enabled
        if (!static::canBeEnabled()) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('archon.trace', CaptureArchonTrace::class);

        // Auto-register middleware globally for zero-config experience
        if ($this->shouldCollect('request')) {
            $router->pushMiddlewareToGroup('web', CaptureArchonTrace::class);
            $router->pushMiddlewareToGroup('api', CaptureArchonTrace::class);
        }

        // Register database hook if enabled
        if ($this->shouldCollect('database')) {
            $options = config('archonflow.options.database', []);
            app(DatabaseHook::class)->register($options['capture_sql'] ?? false);
        }

        // Auto-detect outbound HTTP calls (Http:: facade) — mirrors Debugbar's
        // HttpClientCollector, no manual Archon::trace('external', ...) needed.
        if ($this->shouldCollect('external')) {
            app(ExternalHttpHook::class)->register($this->app->make(Dispatcher::class));
        }

        // Auto-wrap classes under configured namespaces in a tracing proxy so
        // 'service' spans are captured without touching the class itself.
        if ($this->shouldCollect('service')) {
            $namespaces = (array) config('archonflow.trace_namespaces', []);
            if ($namespaces !== []) {
                $cachePath = (string) config('archonflow.services_cache_path', base_path('.archon/services-cache.php'));
                $excludePrefixes = (array) config('archonflow.trace_namespace_excludes', []);
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
        $exceptEnvironments = config('archonflow.except_environments', ['production', 'testing']);

        return !$app->environment($exceptEnvironments);
    }

    /**
     * Check if we should collect data from the specified source
     */
    protected function shouldCollect(string $source): bool
    {
        // If the package is explicitly enabled in config, honor that
        $enabled = config('archonflow.enabled');
        if ($enabled !== null && !$enabled) {
            return false;
        }

        // Check if this specific source is enabled
        return (bool) config("archonflow.sources.{$source}", false);
    }
}
