<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Transport;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Connected mode: pushes each captured trace to Flow API over HTTPS,
 * authenticated per-project (see RFC-0001 / FLOW-IMPLEMENTATION-PROMPT.md).
 * Synchronous, same execution model as TraceWriter's own file write in the
 * same finally block — never throws, so a push failure (Flow API down,
 * network blip) never breaks the traced request itself.
 */
final class HttpPushTransport implements TraceTransport
{
    public function __construct(
        private readonly string $server,
        private readonly string $projectId,
        private readonly string $secretKey,
        private readonly string $version,
        private readonly string $environment,
    ) {}

    public function send(array $flowRecord): void
    {
        try {
            Http::timeout(2)
                ->withToken($this->secretKey)
                ->withHeaders([
                    'X-Flow-Project' => $this->projectId,
                    'X-Flow-Version' => $this->version,
                    'X-Flow-Environment' => $this->environment,
                ])
                ->post(rtrim($this->server, '/').'/api/v1/traces', $flowRecord);
        } catch (Throwable) {
            // Best-effort push — nothing to recover here, and the local
            // flow-history.json file already has this record regardless.
        }
    }
}
