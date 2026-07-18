<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Console;

use Zerethon\Flow\Laravel\Instrumentation\ServiceDiscovery;
use Illuminate\Console\Command;

/**
 * Pre-computes the auto-trace service class manifest so
 * ServiceAutoTraceRegistrar can skip the filesystem walk + Reflection scan
 * on every request. Analogous to `route:cache`/`config:cache` — re-run this
 * after adding, removing, or renaming a class under a trace_namespaces
 * directory, or the manifest will be stale.
 */
final class CacheServicesCommand extends Command
{
    protected $signature = 'flow:cache-services';

    protected $description = 'Cache the auto-traced service class list so ArchonFlow skips the filesystem scan on every request';

    public function handle(ServiceDiscovery $discovery): int
    {
        $namespaces = (array) config('flow.trace_namespaces', []);
        $excludePrefixes = (array) config('flow.trace_namespace_excludes', []);
        $classes = $discovery->discoverAll($namespaces, $excludePrefixes);

        $path = (string) config('flow.services_cache_path', base_path('.zerethon/services-cache.php'));
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($classes, true) . ';' . PHP_EOL);

        $this->components->info(sprintf('Cached %d auto-traced service class(es) to %s', count($classes), $path));

        return self::SUCCESS;
    }
}
