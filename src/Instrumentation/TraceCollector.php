<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TraceCollector
{
    private ?TraceContext $context = null;
    private ?string $controllerNodeId = null;

    public function startRequest(Request $request): void
    {
        $this->context = new TraceContext();
        $this->context->start([
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'route' => $request->route()?->uri(),
        ]);
    }

    public function startController(Request $request): void
    {
        if ($this->context === null) {
            return;
        }

        $action = $request->route()?->getActionName() ?? 'closure';
        $this->controllerNodeId = $this->context->beginNode(
            id: 'controller',
            type: 'controller',
            label: $this->formatControllerLabel($action),
            meta: ['action' => $action],
        );
    }

    public function finishController(): void
    {
        if ($this->context === null || $this->controllerNodeId === null) {
            return;
        }

        $this->context->endNode($this->controllerNodeId);
        $this->controllerNodeId = null;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function traceService(string $label, callable $callback, array $meta = []): mixed
    {
        return $this->traceNode('service', $label, $callback, $meta);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function trace(string $type, string $label, callable $callback, array $meta = []): mixed
    {
        if ($type === "external") {
            return $this->traceExternal($label, $callback, $meta);
        }

        return $this->traceService($label, $callback, $meta);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function traceExternal(string $label, callable $callback, array $meta = []): mixed
    {
        return $this->traceNode('external', $label, $callback, $meta);
    }

    public function recordDatabaseQuery(QueryExecuted $query, bool $captureSql = false): void
    {
        if ($this->context === null) {
            return;
        }

        $querySummary = $this->summarizeSql($query->sql);
        $meta = [
            'connection' => $query->connectionName,
            'query_summary' => $querySummary,
        ];

        if ($captureSql) {
            $meta['sql'] = $query->sql;
        }

        $this->context->addTimedNode(
            type: 'database',
            label: $querySummary,
            durationMs: max(1, (int) round($query->time)),
            meta: $meta,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function finishRequest(?Response $response, ?Throwable $exception = null): ?array
    {
        if ($this->context === null) {
            return null;
        }

        $requestMeta = [
            'http_status' => $response?->getStatusCode() ?? 500,
        ];

        if ($exception !== null) {
            $requestMeta['exception'] = [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $this->context->finish($requestMeta);

        $record = $this->context->toFlowRecord([
            'status' => $exception === null ? 'success' : 'error',
            'errors' => $exception === null ? [] : [$exception->getMessage()],
        ]);

        $this->context = null;
        $this->controllerNodeId = null;

        return $record;
    }

    public function currentTraceId(): ?string
    {
        return $this->context?->traceId;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    private function traceNode(string $type, string $label, callable $callback, array $meta = []): mixed
    {
        if ($this->context === null) {
            return $callback();
        }

        $nodeId = $this->context->beginNode(
            id: null,
            type: $type,
            label: $label,
            meta: $meta,
        );

        try {
            return $callback();
        } finally {
            $this->context->endNode($nodeId);
        }
    }

    private function formatControllerLabel(string $action): string
    {
        if ($action === '' || strtolower($action) === 'closure') {
            return 'closure';
        }

        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            return class_basename($class) . '@' . $method;
        }

        if (class_exists($action)) {
            return class_basename($action) . '@__invoke';
        }

        return $action;
    }

    private function summarizeSql(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? '';
        if ($normalized === '') {
            return 'database query';
        }

        if (strlen($normalized) <= 80) {
            return $normalized;
        }

        return substr($normalized, 0, 77) . '...';
    }
}
