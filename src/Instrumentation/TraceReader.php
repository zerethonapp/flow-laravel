<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

/**
 * Looks a single trace up by ID from the rolling flow-history.json — the
 * file TraceWriter already appends every record to. Backs the
 * GET /_archon/trace/{traceId} endpoint that lets external tools (e.g. the
 * archon-audit crawler) correlate a request it just made with the trace
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
        if (!is_file($this->storagePath)) {
            return null;
        }

        $content = trim((string) file_get_contents($this->storagePath));
        if ($content === '') {
            return null;
        }

        $records = json_decode($content, true);
        if (!is_array($records)) {
            return null;
        }

        foreach ($records as $record) {
            if (is_array($record) && (string) ($record['traceId'] ?? '') === $traceId) {
                return $record;
            }
        }

        return null;
    }
}
