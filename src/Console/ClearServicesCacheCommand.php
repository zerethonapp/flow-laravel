<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Console;

use Illuminate\Console\Command;

final class ClearServicesCacheCommand extends Command
{
    protected $signature = 'archon:clear-services-cache';

    protected $description = 'Remove the cached auto-traced service class manifest';

    public function handle(): int
    {
        $path = (string) config('archonflow.services_cache_path', base_path('.archon/services-cache.php'));

        if (is_file($path)) {
            unlink($path);
            $this->components->info("Removed {$path}");
        } else {
            $this->components->info('Nothing to clear.');
        }

        return self::SUCCESS;
    }
}
