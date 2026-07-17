<?php

declare(strict_types=1);

namespace Tests\Support;

use ArchonFlow\Laravel\Support\Traceable;

/**
 * Self-instruments via the Traceable trait. Also falls under the
 * trace_namespaces auto-proxy scan — must NOT be double-wrapped.
 */
class TraceableAwareService
{
    use Traceable;

    public function run(): string
    {
        return $this->traceService('TraceableAwareService.run', function (): string {
            usleep(5_000);

            return 'ok';
        });
    }
}
