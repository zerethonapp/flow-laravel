<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

use Illuminate\Container\Container as ConcreteContainer;
use Illuminate\Contracts\Container\Container;

/**
 * Registers a container `extend()` decorator for every class discoverable
 * under the configured namespaces, so instances built via the container
 * come back wrapped in a TracingProxyFactory proxy with zero changes to the
 * class itself.
 *
 * Discovery is a filesystem walk + Reflection check per class, which is
 * real per-boot cost on classic PHP-FPM (re-run on every request, unlike
 * Octane). If a cache manifest built by `flow:cache-services` exists at
 * $cachePath, it's used instead of scanning the filesystem live.
 */
final class ServiceAutoTraceRegistrar
{
    public function __construct(
        private readonly ServiceDiscovery $discovery = new ServiceDiscovery(),
    ) {}

    /**
     * @param array<string, string> $namespaces namespace prefix => base directory
     * @param list<string> $excludePrefixes namespace prefixes to skip (e.g. App\Http\Controllers)
     */
    public function register(Container $app, array $namespaces, ?string $cachePath = null, array $excludePrefixes = []): void
    {
        $prefixes = $this->prefixes($namespaces);
        if ($prefixes === []) {
            return;
        }

        $discovered = [];

        foreach ($this->resolveClasses($namespaces, $cachePath, $excludePrefixes) as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $discovered[$class] = true;
            $this->bindConcrete($app, $class);
        }

        // A service resolved through an interface/alias binding (constructor
        // type-hinted to the interface, e.g. bind(FooInterface::class,
        // FooService::class)) is requested from the container as the
        // interface, not the concrete class. Container::extend() is keyed by
        // the exact abstract requested, so a proxy registered only under the
        // concrete class name would never fire for these. Cover every bound
        // abstract too, guarded by the same namespace check. This part is
        // always a live, in-memory read (array_keys on an already-built
        // array) — cheap enough that it doesn't need its own cache.
        if ($app instanceof ConcreteContainer) {
            foreach (array_keys($app->getBindings()) as $abstract) {
                if (!is_string($abstract) || isset($discovered[$abstract])) {
                    continue;
                }

                $this->bindAbstract($app, $abstract, $prefixes, $excludePrefixes);
            }
        }
    }

    /**
     * @param array<string, string> $namespaces
     * @param list<string> $excludePrefixes
     * @return list<string>
     */
    private function resolveClasses(array $namespaces, ?string $cachePath, array $excludePrefixes): array
    {
        if ($cachePath !== null && is_file($cachePath)) {
            /** @var mixed $cached */
            $cached = require $cachePath;

            if (is_array($cached)) {
                return array_values(array_filter($cached, 'is_string'));
            }
        }

        return $this->discovery->discoverAll($namespaces, $excludePrefixes);
    }

    /**
     * @param array<string, string> $namespaces
     * @return list<string>
     */
    private function prefixes(array $namespaces): array
    {
        $prefixes = [];

        foreach (array_keys($namespaces) as $namespace) {
            if (is_string($namespace) && $namespace !== '') {
                $prefixes[] = rtrim($namespace, '\\') . '\\';
            }
        }

        return $prefixes;
    }

    /**
     * @param class-string $class
     */
    private function bindConcrete(Container $app, string $class): void
    {
        $app->extend($class, static function (mixed $service, Container $app) use ($class): mixed {
            if (!$service instanceof $class) {
                return $service;
            }

            return TracingProxyFactory::wrap($service, $app->make(TraceCollector::class));
        });
    }

    /**
     * @param list<string> $prefixes
     * @param list<string> $excludePrefixes
     */
    private function bindAbstract(Container $app, string $abstract, array $prefixes, array $excludePrefixes): void
    {
        $app->extend($abstract, static function (mixed $service, Container $app) use ($prefixes, $excludePrefixes): mixed {
            // Not every container binding resolves to an object — Laravel
            // binds plain strings too (e.g. 'path.storage').
            if (!is_object($service)) {
                return $service;
            }

            $class = $service::class;

            foreach ($excludePrefixes as $excluded) {
                if (is_string($excluded) && $excluded !== '' && str_starts_with($class, rtrim($excluded, '\\') . '\\')) {
                    return $service;
                }
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($class, $prefix)) {
                    return TracingProxyFactory::wrap($service, $app->make(TraceCollector::class));
                }
            }

            return $service;
        });
    }
}
