<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Deliberately plain: no Traceable trait, no Archon::trace() call. Only
 * ever requested from the container via its interface, never by its own
 * class name — proves interface-bound resolution is auto-traced too.
 */
class GreetingService implements GreetingServiceInterface
{
    public function greet(): string
    {
        usleep(5_000);

        return 'hello';
    }
}
