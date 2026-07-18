<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Deliberately plain: no Traceable trait, no Flow::trace() call, no
 * interface. Proves the container-resolved tracing proxy captures a
 * 'service' span without any code in the class itself.
 */
class AutoTracedService
{
    public function run(): string
    {
        usleep(5_000);

        return 'ok';
    }
}
