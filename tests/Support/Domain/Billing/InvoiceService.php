<?php

declare(strict_types=1);

namespace Tests\Support\Domain\Billing;

/**
 * Deeply nested, DDD-style location. Deliberately plain — proves whole-tree
 * auto-scan finds business logic regardless of folder depth/convention,
 * without the user having to list this specific subfolder in config.
 */
class InvoiceService
{
    public function issue(): string
    {
        usleep(5_000);

        return 'issued';
    }
}
