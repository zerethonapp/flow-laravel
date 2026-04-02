<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

use ArchonFlow\Laravel\Support\GeneratesTraceIds;
use Throwable;

final class TraceCollector
{
    use GeneratesTraceIds;

    private string $traceId;
    private float $traceStartedAtMs;
    private ?float $traceEndedAtMs = null;
    private string $requestNodeId = "request";
    private int $counter = 0;

    /** @var array<string, array<string, mixed>> */
    private array $nodesById = [];

    /** @var array<string, array{from: string, to: string, type: string}> */
    private array $edgesByKey = [];

    private readonly TraceContext $context;

    public function __construct(
        private readonly NodeBuilder $nodeBuilder = new NodeBuilder(),
        private readonly EdgeBuilder $edgeBuilder = new EdgeBuilder(),
    ) {
        $this->context = new TraceContext();
    }

    /**
     * @param array<string, mixed> $requestMeta
     */
    public function startTrace(array $requestMeta = []): void
    {
        $this->traceId = $this->generateTraceId();
        $this->traceStartedAtMs = $this->nowMs();

        $this->startScopedNode(
            id: $this->requestNodeId,
            type: "request",
            label: "HTTP Request",
            meta: $requestMeta,
            parentId: null,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function startScopedNode(
        ?string $id,
        string $type,
        string $label,
        array $meta = [],
        ?string $parentId = null,
    ): string {
        $nodeId = $id ?? $this->nextNodeId($type);
        $startedAt = $this->nowMs();

        $effectiveParentId = $parentId ?? $this->context->current();
        if ($effectiveParentId !== null) {
            $edge = $this->edgeBuilder->make($effectiveParentId, $nodeId, "call");
            $this->edgesByKey[$this->edgeKey($edge)] = $edge;
        }

        $this->nodesById[$nodeId] = $this->nodeBuilder->make(
            id: $nodeId,
            type: $type,
            label: $label,
            startMs: $startedAt,
            endMs: $startedAt,
            meta: $meta,
        );
        $this->context->setStart($nodeId, $startedAt);
        $this->context->push($nodeId);

        return $nodeId;
    }

    public function finishNode(string $nodeId): void
    {
        $start = $this->context->getStart($nodeId);
        if ($start === null || !isset($this->nodesById[$nodeId])) {
            return;
        }

        $end = $this->nowMs();
        $node = $this->nodesById[$nodeId];
        $label = (string) (($node["meta"]["label"] ?? $node["id"]) ?: $node["id"]);
        $meta = is_array($node["meta"] ?? null) ? $node["meta"] : [];

        $this->nodesById[$nodeId] = $this->nodeBuilder->make(
            id: (string) $node["id"],
            type: (string) $node["type"],
            label: $label,
            startMs: $start,
            endMs: $end,
            meta: $meta,
        );

        $this->context->removeStart($nodeId);
        $this->context->pop($nodeId);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function recordTimedNode(
        string $type,
        string $label,
        float $durationMs,
        array $meta = [],
        ?string $parentId = null,
    ): string {
        $nodeId = $this->nextNodeId($type);
        $end = $this->nowMs();
        $safeDuration = max(1, (int) round($durationMs));
        $start = max(0, $end - $safeDuration);
        $effectiveParentId = $parentId ?? $this->context->current();

        if ($effectiveParentId !== null) {
            $edge = $this->edgeBuilder->make($effectiveParentId, $nodeId, "call");
            $this->edgesByKey[$this->edgeKey($edge)] = $edge;
        }

        $this->nodesById[$nodeId] = $this->nodeBuilder->make(
            id: $nodeId,
            type: $type,
            label: $label,
            startMs: $start,
            endMs: $end,
            meta: $meta,
        );

        return $nodeId;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function trace(
        string $type,
        string $label,
        callable $callback,
        array $meta = [],
        ?string $parentId = null,
    ): mixed {
        $nodeId = $this->startScopedNode(
            id: null,
            type: $type,
            label: $label,
            meta: $meta,
            parentId: $parentId,
        );

        try {
            return $callback();
        } finally {
            $this->finishNode($nodeId);
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function finishTrace(array $meta = []): void
    {
        foreach (array_keys($this->context->activeStarts()) as $nodeId) {
            $this->finishNode($nodeId);
        }

        $this->traceEndedAtMs = $this->nowMs();
        if ($meta !== [] && isset($this->nodesById[$this->requestNodeId])) {
            $requestNode = $this->nodesById[$this->requestNodeId];
            $existingMeta = is_array($requestNode["meta"] ?? null) ? $requestNode["meta"] : [];
            $requestNode["meta"] = [...$existingMeta, ...$meta];
            $this->nodesById[$this->requestNodeId] = $requestNode;
        }
    }

    public function getTraceId(): ?string
    {
        return $this->traceId ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFlowModel(): array
    {
        $nodes = array_values($this->nodesById);
        usort(
            $nodes,
            static fn (array $a, array $b): int => ((float) $a["start_time"] <=> (float) $b["start_time"]),
        );

        return [
            "schema_version" => "1.0",
            "trace_id" => $this->traceId,
            "timestamp" => (int) floor(($this->traceStartedAtMs ?? $this->nowMs()) / 1000),
            "nodes" => $nodes,
            "edges" => array_values($this->edgesByKey),
            "meta" => [
                "startedAt" => $this->traceStartedAtMs,
                "endedAt" => $this->traceEndedAtMs,
                "totalTime" => $this->totalTimeMs(),
            ],
        ];
    }

    /**
     * @param array{status?: string, errors?: array<int, string>} $resultMeta
     * @return array<string, mixed>
     */
    public function toFlowRecord(array $resultMeta = []): array
    {
        $flow = $this->toFlowModel();
        $executedNodes = array_map(
            static fn (array $node): string => (string) $node["id"],
            $flow["nodes"],
        );

        $result = [
            "traceId" => $this->traceId,
            "totalTime" => $this->totalTimeMs(),
            "nodeCount" => count($executedNodes),
            "status" => $resultMeta["status"] ?? "success",
            "executedNodes" => $executedNodes,
        ];

        if (($resultMeta["errors"] ?? []) !== []) {
            $result["errors"] = $resultMeta["errors"];
        }

        return [
            "traceId" => $this->traceId,
            "timestamp" => (int) floor($this->nowMs() / 1000),
            "flow" => $flow,
            "result" => $result,
        ];
    }

    public function captureException(Throwable $throwable): void
    {
        if (!isset($this->nodesById[$this->requestNodeId])) {
            return;
        }

        $requestNode = $this->nodesById[$this->requestNodeId];
        $meta = is_array($requestNode["meta"] ?? null) ? $requestNode["meta"] : [];
        $meta["exception"] = [
            "type" => $throwable::class,
            "message" => $throwable->getMessage(),
        ];
        $requestNode["meta"] = $meta;
        $this->nodesById[$this->requestNodeId] = $requestNode;
    }

    private function nextNodeId(string $type): string
    {
        $this->counter++;
        return sprintf("%s_%d", $type, $this->counter);
    }

    /**
     * @param array{from: string, to: string, type: string} $edge
     */
    private function edgeKey(array $edge): string
    {
        return sprintf("%s>%s>%s", $edge["from"], $edge["to"], $edge["type"]);
    }

    private function nowMs(): float
    {
        return (float) (int) round(microtime(true) * 1000);
    }

    private function totalTimeMs(): float
    {
        if (!isset($this->traceStartedAtMs)) {
            return 0;
        }

        $endedAt = $this->traceEndedAtMs ?? $this->nowMs();
        return (float) max(1, (int) round($endedAt - $this->traceStartedAtMs));
    }
}
