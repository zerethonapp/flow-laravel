<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

/**
 * Looks a single trace up by ID from the rolling flow-history.json — the
 * file TraceWriter already appends every record to. Backs the
 * GET /_archon/trace/{traceId} endpoint that lets external tools (e.g. the
 * flow-audit crawler) correlate a request it just made with the trace
 * that request produced, over HTTP rather than assuming filesystem access.
 */
final class TraceReader
{
    public function __construct(
        private readonly string $storagePath,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $traceId): ?array
    {
        foreach ($this->readAll() as $record) {
            if ((string) ($record['traceId'] ?? '') === $traceId) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Newest-first, capped at $limit — backs GET /_archon/traces so a
     * caller can browse everything flow-laravel has captured for this
     * site, not just a trace it already knows the ID of.
     *
     * @return list<array<string, mixed>>
     */
    public function all(int $limit = 50): array
    {
        $records = array_reverse($this->readAll());

        return array_slice($records, 0, max(0, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readAll(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $content = trim((string) file_get_contents($this->storagePath));
        if ($content === '') {
            return [];
        }

        $records = json_decode($content, true);
        if (!is_array($records)) {
            return [];
        }

        return array_values(array_filter($records, static fn ($record): bool => is_array($record)));
    }
}
