<?php

namespace Tests\Support;

/** Adapter is push-only — no /_flow/trace* routes to read back from, so tests that need to inspect a captured record read the local flow-history.json file TraceWriter always writes, same as Offline-mode manual upload would. */
trait ReadsLocalTraceFile
{
    /** @return array<string, mixed>|null */
    private function readTraceRecord(string $traceId): ?array
    {
        foreach ($this->readAllTraceRecords() as $record) {
            if (($record['traceId'] ?? null) === $traceId) {
                return $record;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function readAllTraceRecords(): array
    {
        $path = (string) config('flow.storage_path');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
