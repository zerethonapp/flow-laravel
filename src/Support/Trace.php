<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Support;

use Attribute;

/**
 * Trace attribute for automatic method tracing
 *
 * Usage:
 * #[Trace('service')]
 * public function processOrder() { ... }
 *
 * #[Trace('external', 'PaymentAPI.charge')]
 * public function chargePayment() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Trace
{
    public function __construct(
        public readonly string $type = 'service',
        public readonly ?string $label = null,
    ) {}
}
