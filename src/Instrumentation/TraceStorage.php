<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

final class TraceStorage
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxRecords = 1000,
    ) {}

    /**
     * @param array<string, mixed> $record
     */
    public function appendRecord(array $record): void
    {
        $directory = dirname($this->storagePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $records = $this->readRecords();

        $records = array_values(
            array_filter(
                $records,
                static fn (array $item): bool => ($item["traceId"] ?? null) !== ($record["traceId"] ?? null),
            ),
        );
        $records[] = $record;

        $max = max(1, $this->maxRecords);
        if (count($records) > $max) {
            $records = array_slice($records, -$max);
        }

        file_put_contents(
            $this->storagePath,
            json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRecords(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $content = trim((string) file_get_contents($this->storagePath));
        if ($content === "") {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}
