<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

use FilesystemIterator;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Walks configured namespace directories and returns every instantiable
 * class found. Shared by ServiceAutoTraceRegistrar (live scan, used when no
 * cache manifest exists) and the archon:cache-services console command
 * (pre-computes the manifest so the live scan can be skipped on request).
 */
final class ServiceDiscovery
{
    /**
     * @param array<string, string> $namespaces namespace prefix => base directory
     * @param list<string> $excludePrefixes namespace prefixes to skip (e.g. App\Http\Controllers)
     * @return list<class-string>
     */
    public function discoverAll(array $namespaces, array $excludePrefixes = []): array
    {
        $classes = [];

        foreach ($namespaces as $namespace => $directory) {
            if (!is_string($directory) || !is_string($namespace) || $namespace === '' || !is_dir($directory)) {
                continue;
            }

            foreach ($this->discover($namespace, $directory, $excludePrefixes) as $class) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @param list<string> $excludePrefixes
     * @return list<class-string>
     */
    private function discover(string $namespace, string $directory, array $excludePrefixes): array
    {
        $classes = [];
        $namespace = rtrim($namespace, '\\');
        $directory = rtrim($directory, '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = $namespace . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if ($this->isExcluded($class, $excludePrefixes)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);
            } catch (Throwable) {
                continue;
            }

            if (!$reflection->isInstantiable()) {
                continue;
            }

            /** @var class-string $class */
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param list<string> $excludePrefixes
     */
    private function isExcluded(string $class, array $excludePrefixes): bool
    {
        foreach ($excludePrefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($class, rtrim($prefix, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }
}
