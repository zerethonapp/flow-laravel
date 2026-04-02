<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Support;

trait GeneratesTraceIds
{
    protected function generateTraceId(): string
    {
        return sprintf('trace_%s', bin2hex(random_bytes(8)));
    }
}
