<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class InstrumentationManager
{
    private ?TraceCollector $collector = null;
    private ?string $controllerNodeId = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly TraceStorage $storage,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) ($this->config["enabled"] ?? true);
    }

    public function startRequest(Request $request): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->collector = new TraceCollector();
        $this->collector->startTrace([
            "method" => $request->getMethod(),
            "uri" => $request->getRequestUri(),
            "route" => $request->route()?->uri(),
        ]);
    }

    public function startController(Request $request): void
    {
        if (!$this->isEnabled() || $this->collector === null) {
            return;
        }

        if (!(bool) ($this->config["capture_controller"] ?? true)) {
            return;
        }

        $action = $request->route()?->getActionName() ?? "closure";
        $this->controllerNodeId = $this->collector->startScopedNode(
            id: "controller",
            type: "controller",
            label: "Controller Action",
            meta: ["action" => $action],
        );
    }

    public function finishController(): void
    {
        if ($this->collector === null || $this->controllerNodeId === null) {
            return;
        }

        $this->collector->finishNode($this->controllerNodeId);
        $this->controllerNodeId = null;
    }

    public function finishRequest(?Response $response, ?Throwable $exception = null): void
    {
        if ($this->collector === null) {
            return;
        }

        if ($exception !== null) {
            $this->collector->captureException($exception);
        }

        $this->collector->finishTrace([
            "http_status" => $response?->getStatusCode() ?? 500,
        ]);

        $status = $exception === null ? "success" : "error";
        $errors = $exception === null ? [] : [$exception->getMessage()];
        $record = $this->collector->toFlowRecord([
            "status" => $status,
            "errors" => $errors,
        ]);
        $this->storage->appendRecord($record);

        $this->collector = null;
        $this->controllerNodeId = null;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function traceService(
        string $label,
        callable $callback,
        array $meta = [],
    ): mixed {
        if ($this->collector === null) {
            return $callback();
        }

        return $this->collector->trace("service", $label, $callback, $meta);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function traceExternal(
        string $label,
        callable $callback,
        array $meta = [],
    ): mixed {
        if ($this->collector === null) {
            return $callback();
        }

        return $this->collector->trace("external", $label, $callback, $meta);
    }

    public function recordDatabaseQuery(QueryExecuted $query): void
    {
        if ($this->collector === null) {
            return;
        }

        $meta = [
            "connection" => $query->connectionName,
        ];

        if ((bool) ($this->config["capture_query_sql"] ?? false)) {
            $meta["sql"] = $query->sql;
        }

        $this->collector->recordTimedNode(
            type: "database",
            label: "DB Query",
            durationMs: (float) $query->time,
            meta: $meta,
        );
    }

    public function currentTraceId(): ?string
    {
        return $this->collector?->getTraceId();
    }
}
