<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Providers;

use ArchonFlow\Laravel\Instrumentation\Hooks\DatabaseHook;
use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;
use ArchonFlow\Laravel\Instrumentation\TraceStorage;
use ArchonFlow\Laravel\Middleware\CaptureArchonTrace;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class ArchonFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . "/../../config/archonflow.php", "archonflow");

        $this->app->singleton(TraceStorage::class, function (): TraceStorage {
            $path = (string) config("archonflow.storage_path", base_path(".archon/flow-history.json"));
            $max = (int) config("archonflow.max_records", 1000);
            return new TraceStorage($path, $max);
        });

        $this->app->singleton(InstrumentationManager::class, function (): InstrumentationManager {
            /** @var array<string, mixed> $config */
            $config = (array) config("archonflow", []);
            return new InstrumentationManager(
                config: $config,
                storage: app(TraceStorage::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . "/../../config/archonflow.php" => config_path("archonflow.php")],
            "archonflow-config",
        );

        /** @var Router $router */
        $router = $this->app->make("router");
        $router->aliasMiddleware("archon.trace", CaptureArchonTrace::class);

        if ((bool) config("archonflow.enabled", true)) {
            app(DatabaseHook::class)->register();
        }
    }
}
