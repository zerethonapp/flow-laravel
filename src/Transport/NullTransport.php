<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Transport;

/** Used when Connected mode (FLOW_SERVER/FLOW_PROJECT_ID/FLOW_SECRET_KEY) isn't configured — Offline/CLI modes rely solely on the local flow-history.json file, no push attempted. */
final class NullTransport implements TraceTransport
{
    public function send(array $flowRecord): void
    {
        // Intentionally a no-op.
    }
}
