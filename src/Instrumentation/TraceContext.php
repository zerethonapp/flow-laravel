<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

final class TraceContext
{
    public string $traceId;
    public int $startedAtMs;
    public ?int $endedAtMs = null;

    /** @var array<int, string> */
    public array $stack = [];

    /** @var array<string, int> */
    public array $nodeStartTimes = [];

    /** @var array<string, array<string, mixed>> */
    public array $nodes = [];

    /** @var array<string, array{from: string, to: string, type: string}> */
    public array $edges = [];

    private int $counter = 0;

    /**
     * @param array<string, mixed> $requestMeta
     */
    public function start(array $requestMeta = []): void
    {
        $this->traceId = $this->generateTraceId();
        $this->startedAtMs = $this->nowMs();
        $this->stack = [];
        $this->nodeStartTimes = [];
        $this->nodes = [];
        $this->edges = [];
        $this->counter = 0;

        $this->beginNode(
            id: 'request',
            type: 'request',
            label: 'HTTP Request',
            meta: $requestMeta,
            parentId: null,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function beginNode(
        ?string $id,
        string $type,
        string $label,
        array $meta = [],
        ?string $parentId = null,
    ): string {
        $nodeId = $id ?? $this->nextNodeId($type);
        $start = $this->nowMs();
        $parent = $parentId ?? $this->currentNodeId();

        if ($parent !== null) {
            $edge = [
                'from' => $parent,
                'to' => $nodeId,
                'type' => 'call',
            ];
            $this->edges[$this->edgeKey($edge)] = $edge;
        }

        $this->nodes[$nodeId] = [
            'id' => $nodeId,
            'type' => $type,
            'label' => $label,
            'start_time' => $start,
            'end_time' => $start + 1,
            'duration' => 1,
            'meta' => $meta,
        ];

        $this->nodeStartTimes[$nodeId] = $start;
        $this->stack[] = $nodeId;

        return $nodeId;
    }

    public function endNode(string $nodeId): void
    {
        if (!isset($this->nodes[$nodeId], $this->nodeStartTimes[$nodeId])) {
            return;
        }

        $start = $this->nodeStartTimes[$nodeId];
        $end = $this->nowMs();
        if ($end <= $start) {
            $end = $start + 1;
        }

        $this->nodes[$nodeId]['end_time'] = $end;
        $this->nodes[$nodeId]['duration'] = $end - $start;

        unset($this->nodeStartTimes[$nodeId]);
        $this->removeFromStack($nodeId);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function addTimedNode(
        string $type,
        string $label,
        int $durationMs,
        array $meta = [],
        ?string $parentId = null,
    ): string {
        $nodeId = $this->nextNodeId($type);
        $end = $this->nowMs();
        $safeDuration = max(1, $durationMs);
        $parent = $parentId ?? $this->currentNodeId();
        $start = max(0, $end - $safeDuration);

        if ($parent !== null) {
            $parentStart = isset($this->nodes[$parent]['start_time'])
                ? (int) $this->nodes[$parent]['start_time']
                : null;

            if ($parentStart !== null) {
                $start = max($start, $parentStart);
            }
        }

        if ($end <= $start) {
            $end = $start + 1;
        }

        if ($parent !== null) {
            $edge = [
                'from' => $parent,
                'to' => $nodeId,
                'type' => 'call',
            ];
            $this->edges[$this->edgeKey($edge)] = $edge;
        }

        $this->nodes[$nodeId] = [
            'id' => $nodeId,
            'type' => $type,
            'label' => $label,
            'start_time' => $start,
            'end_time' => $end,
            'duration' => $end - $start,
            'meta' => $meta,
        ];

        return $nodeId;
    }

    public function currentNodeId(): ?string
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    /**
     * @param array<string, mixed> $requestMeta
     */
    public function finish(array $requestMeta = []): void
    {
        foreach (array_keys($this->nodeStartTimes) as $nodeId) {
            $this->endNode($nodeId);
        }

        $this->endedAtMs = $this->nowMs();

        if (isset($this->nodes['request'])) {
            $meta = is_array($this->nodes['request']['meta'] ?? null) ? $this->nodes['request']['meta'] : [];
            $this->nodes['request']['meta'] = [...$meta, ...$requestMeta];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toFlowModel(): array
    {
        $nodes = array_values($this->nodes);
        usort(
            $nodes,
            static fn (array $a, array $b): int => ((int) $a['start_time'] <=> (int) $b['start_time']),
        );

        return [
            'schema_version' => '1.0',
            'trace_id' => $this->traceId,
            'timestamp' => (int) floor($this->startedAtMs / 1000),
            'nodes' => $nodes,
            'edges' => array_values($this->edges),
            'meta' => [
                'startedAt' => $this->startedAtMs,
                'endedAt' => $this->endedAtMs,
                'totalTime' => $this->totalTime(),
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
            static fn (array $node): string => (string) $node['id'],
            $flow['nodes'],
        );

        $result = [
            'traceId' => $this->traceId,
            'totalTime' => $this->totalTime(),
            'nodeCount' => count($executedNodes),
            'status' => $resultMeta['status'] ?? 'success',
            'executedNodes' => $executedNodes,
        ];

        if (($resultMeta['errors'] ?? []) !== []) {
            $result['errors'] = $resultMeta['errors'];
        }

        return [
            'traceId' => $this->traceId,
            'timestamp' => (int) floor(($this->endedAtMs ?? $this->nowMs()) / 1000),
            'flow' => $flow,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSimpleTrace(): array
    {
        return [
            'traceId' => $this->traceId,
            'totalTime' => $this->totalTime(),
            'nodes' => array_values($this->nodes),
            'edges' => array_values($this->edges),
        ];
    }

    private function totalTime(): int
    {
        if (!isset($this->startedAtMs)) {
            return 0;
        }

        $endedAt = $this->endedAtMs ?? $this->nowMs();
        return max(1, $endedAt - $this->startedAtMs);
    }

    private function removeFromStack(string $nodeId): void
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if ($this->stack[$i] === $nodeId) {
                array_splice($this->stack, $i, 1);
                return;
            }
        }
    }

    private function nextNodeId(string $type): string
    {
        $this->counter++;
        return sprintf('%s_%d', $type, $this->counter);
    }

    /**
     * @param array{from: string, to: string, type: string} $edge
     */
    private function edgeKey(array $edge): string
    {
        return sprintf('%s>%s>%s', $edge['from'], $edge['to'], $edge['type']);
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    private function generateTraceId(): string
    {
        return sprintf('trace_%s', bin2hex(random_bytes(8)));
    }
}
