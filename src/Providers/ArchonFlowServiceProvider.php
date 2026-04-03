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
    }

    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . '/../../config/archonflow.php' => config_path('archonflow.php')],
            'archonflow-config',
        );

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('archon.trace', CaptureArchonTrace::class);

        if ((bool) config('archonflow.enabled', true)) {
            app(DatabaseHook::class)->register((bool) config('archonflow.capture_query_sql', false));
        }
    }
}
