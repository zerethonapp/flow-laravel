<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

final class NodeBuilder
{
    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function make(
        string $id,
        string $type,
        string $label,
        float $startMs,
        float $endMs,
        array $meta = [],
    ): array {
        $safeStartMs = (int) round($startMs);
        $safeEndMs = (int) round($endMs);

        if ($safeEndMs <= $safeStartMs) {
            $safeEndMs = $safeStartMs + 1;
        }

        $duration = $safeEndMs - $safeStartMs;

        return [
            "id" => $id,
            "type" => $type,
            "start_time" => $safeStartMs,
            "end_time" => $safeEndMs,
            "duration" => $duration,
            "meta" => ["label" => $label, ...$meta],
        ];
    }
}
