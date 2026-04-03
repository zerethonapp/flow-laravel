<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Providers;

use ArchonFlow\Laravel\Instrumentation\Hooks\DatabaseHook;
use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use ArchonFlow\Laravel\Instrumentation\TraceWriter;
use ArchonFlow\Laravel\Middleware\CaptureArchonTrace;
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
            $this->app['router']->pushMiddlewareToGroup('web', CaptureArchonTrace::class);
            $this->app['router']->pushMiddlewareToGroup('api', CaptureArchonTrace::class);
        }

        // Register database hook if enabled
        if ($this->shouldCollect('database')) {
            $options = config('archonflow.options.database', []);
            app(DatabaseHook::class)->register($options['capture_sql'] ?? false);
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
