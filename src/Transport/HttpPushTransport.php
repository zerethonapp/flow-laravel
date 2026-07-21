<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connected mode: pushes each captured trace to Flow API over HTTPS,
 * authenticated per-project (see RFC-0001 / FLOW-IMPLEMENTATION-PROMPT.md).
 * Synchronous, same execution model as TraceWriter's own file write in the
 * same finally block — never throws, so a push failure (Flow API down,
 * network blip) never breaks the traced request itself. Failures are logged
 * (host app's own default log channel) rather than silently swallowed —
 * previously there was zero signal anywhere when Connected mode broke.
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
        $traceId = (string) ($flowRecord['traceId'] ?? 'unknown');

        try {
            $response = Http::timeout(2)
                ->withToken($this->secretKey)
                ->withHeaders([
                    'X-Flow-Project' => $this->projectId,
                    'X-Flow-Version' => $this->version,
                    'X-Flow-Environment' => $this->environment,
                ])
                ->post(rtrim($this->server, '/').'/api/v1/traces', $flowRecord);

            if (! $response->successful()) {
                Log::warning('[flow] Connected-mode push rejected by Flow API', [
                    'traceId' => $traceId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            // Best-effort push — nothing to recover here, and the local
            // flow-history.json file already has this record regardless.
            // Logged so a broken Connected-mode setup (wrong FLOW_SERVER,
            // network egress blocked, Flow API down) is at least visible in
            // the host app's own logs instead of failing invisibly forever.
            Log::warning('[flow] Connected-mode push failed', [
                'traceId' => $traceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
