<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

final class TraceWriter
{
    public function __construct(
        private readonly string $storagePath,
        private readonly string $traceDirectory,
        private readonly int $maxRecords = 1000,
    ) {}

    /**
     * @param array<string, mixed> $flowRecord
     */
    public function write(array $flowRecord): void
    {
        $this->writeSimpleTraceFile($flowRecord);

        $directory = dirname($this->storagePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $records = $this->read();
        $traceId = (string) ($flowRecord["traceId"] ?? "");
        $records = array_values(
            array_filter(
                $records,
                static fn (array $record): bool => (string) ($record["traceId"] ?? "") !== $traceId,
            ),
        );
        $records[] = $flowRecord;

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
     * @param array<string, mixed> $flowRecord
     */
    private function writeSimpleTraceFile(array $flowRecord): void
    {
        if (!is_dir($this->traceDirectory)) {
            mkdir($this->traceDirectory, 0777, true);
        }

        $traceId = (string) ($flowRecord["traceId"] ?? "");
        $flow = is_array($flowRecord["flow"] ?? null) ? $flowRecord["flow"] : [];
        $flowMeta = is_array($flow["meta"] ?? null) ? $flow["meta"] : [];
        $nodes = is_array($flow["nodes"] ?? null) ? $flow["nodes"] : [];
        $edges = is_array($flow["edges"] ?? null) ? $flow["edges"] : [];
        $totalTime = (int) ($flowRecord["result"]["totalTime"] ?? 0);
        $requestNode = null;
        foreach ($nodes as $node) {
            if (($node["type"] ?? null) === "request") {
                $requestNode = is_array($node) ? $node : null;
                break;
            }
        }
        $requestMeta = [];
        if (is_array($flowMeta["request"] ?? null)) {
            $requestMeta = $flowMeta["request"];
        } elseif (is_array($requestNode["meta"] ?? null)) {
            $requestMeta = $requestNode["meta"];
        }

        $simpleTrace = [
            "traceId" => $traceId,
            "totalTime" => $totalTime,
            "request" => [
                "method" => (string) ($requestMeta["method"] ?? ""),
                "uri" => (string) ($requestMeta["uri"] ?? $requestMeta["route"] ?? ""),
                "route" => (string) ($requestMeta["route"] ?? ""),
            ],
            "nodes" => array_map(
                static function (array $node): array {
                    return [
                        "id" => (string) ($node["id"] ?? ""),
                        "type" => (string) ($node["type"] ?? ""),
                        "label" => (string) (($node["label"] ?? $node["id"] ?? "")),
                        "start_time" => (int) ($node["start_time"] ?? 0),
                        "end_time" => (int) ($node["end_time"] ?? 0),
                        "duration" => (int) ($node["duration"] ?? 0),
                    ];
                },
                $nodes,
            ),
            "edges" => $edges,
        ];

        $filePath = rtrim($this->traceDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $traceId . ".json";
        file_put_contents($filePath, json_encode($simpleTrace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
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
