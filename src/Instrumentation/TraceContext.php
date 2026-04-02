<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

final class TraceContext
{
    /** @var array<int, string> */
    private array $stack = [];

    /** @var array<string, float> */
    private array $startedAt = [];

    public function push(string $nodeId): void
    {
        $this->stack[] = $nodeId;
    }

    public function pop(?string $expectedNodeId = null): ?string
    {
        if ($expectedNodeId === null) {
            return array_pop($this->stack);
        }

        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if ($this->stack[$i] === $expectedNodeId) {
                array_splice($this->stack, $i, 1);
                return $expectedNodeId;
            }
        }

        return null;
    }

    public function current(): ?string
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }

    public function setStart(string $nodeId, float $ms): void
    {
        $this->startedAt[$nodeId] = $ms;
    }

    public function getStart(string $nodeId): ?float
    {
        return $this->startedAt[$nodeId] ?? null;
    }

    public function removeStart(string $nodeId): void
    {
        unset($this->startedAt[$nodeId]);
    }

    /**
     * @return array<string, float>
     */
    public function activeStarts(): array
    {
        return $this->startedAt;
    }
}
