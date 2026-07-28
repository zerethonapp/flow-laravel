<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Instrumentation;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TraceCollector
{
    private ?TraceContext $context = null;
    private ?string $controllerNodeId = null;
    private ?Throwable $reportedException = null;

    public function startRequest(Request $request): void
    {
        $uri = $request->getRequestUri();

        if ($this->maskingEnabled()) {
            $uri = Masker::maskRequestUri($uri, $request->route()?->parameters() ?? []);
        }

        $this->context = new TraceContext();
        $this->context->start([
            'method' => $request->getMethod(),
            'uri' => $uri,
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
     * Open a service span without a callback wrapper. Used by the auto-trace
     * proxy so it can preserve by-reference/variadic parameter forwarding
     * around the real method call instead of going through a closure.
     *
     * @param array<string, mixed> $meta
     */
    public function beginServiceSpan(string $label, array $meta = []): ?string
    {
        if ($this->context === null) {
            return null;
        }

        return $this->context->beginNode(
            id: null,
            type: 'service',
            label: $label,
            meta: $this->maskingEnabled() ? Masker::maskArray($meta) : $meta,
        );
    }

    public function endServiceSpan(?string $nodeId): void
    {
        if ($this->context === null || $nodeId === null) {
            return;
        }

        $this->context->endNode($nodeId);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $meta
     * @return T
     */
    public function trace(string $type, string $label, callable $callback, array $meta = []): mixed
    {
        return $this->traceNode($type, $label, $callback, $meta);
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
     * Record an outbound HTTP call captured automatically via
     * Illuminate\Http\Client events (no manual instrumentation required).
     *
     * @param array<string, mixed> $meta
     */
    public function recordExternalCall(string $label, int $durationMs, array $meta = []): void
    {
        if ($this->context === null) {
            return;
        }

        $this->context->addTimedNode(
            type: 'external',
            label: $label,
            durationMs: max(1, $durationMs),
            meta: $this->maskingEnabled() ? Masker::maskArray($meta) : $meta,
        );
    }

    /**
     * Records a request-lifecycle exception the moment Laravel's own
     * exception handler reports it — see FlowServiceProvider's
     * `reportable()` registration for why this exists. `Illuminate\Routing\Pipeline`
     * (the pipeline the 'web'/'api' middleware group — including
     * CaptureFlowTrace itself — runs through) catches any Throwable at the
     * exact pipe it's thrown from and renders it to a Response immediately,
     * so `$next($request)` back in CaptureFlowTrace::handle() never
     * actually throws: it always returns a normal (if error-rendered)
     * Response. That made the `?Throwable $exception` parameter below
     * permanently null in practice — confirmed by a live reproduction
     * before this fix, not by inspection alone. Reporting is the one place
     * upstream of that swallowing where the real exception is still
     * available.
     */
    public function recordException(Throwable $e): void
    {
        if ($this->context !== null) {
            $this->reportedException = $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function finishRequest(?Response $response, ?Throwable $exception = null): ?array
    {
        if ($this->context === null) {
            return null;
        }

        // $exception (passed by CaptureFlowTrace's own try/catch) stays as
        // a defensive fallback for the case where something throws before
        // reaching Laravel's routing pipeline at all — but in the common
        // case, $this->reportedException (set via recordException() above)
        // is what's actually populated.
        $exception ??= $this->reportedException;

        $requestMeta = [
            'http_status' => $response?->getStatusCode() ?? 500,
        ];

        // Exception messages are free text — only the light email-shaped
        // check applies here (Masker::maskString), not the full key-based
        // maskArray() logic, since there's no structured key to match
        // against. A message that happens to embed a raw secret (rather
        // than an email address) is not caught by this — flagged as a
        // known limitation, not silently assumed safe.
        $exceptionMessage = $exception?->getMessage();
        if ($exceptionMessage !== null && $this->maskingEnabled()) {
            $exceptionMessage = Masker::maskString($exceptionMessage);
        }

        if ($exception !== null) {
            $requestMeta['exception'] = [
                'type' => $exception::class,
                'message' => $exceptionMessage,
            ];
        }

        $this->context->finish($requestMeta);

        $record = $this->context->toFlowRecord([
            'status' => $exception === null ? 'success' : 'error',
            'errors' => $exception === null ? [] : [$exceptionMessage],
        ]);

        $this->context = null;
        $this->controllerNodeId = null;
        $this->reportedException = null;

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
            meta: $this->maskingEnabled() ? Masker::maskArray($meta) : $meta,
        );

        try {
            return $callback();
        } finally {
            $this->context->endNode($nodeId);
        }
    }

    /**
     * Config is read directly here (rather than threaded through as a
     * constructor/method parameter, the way recordDatabaseQuery()'s
     * $captureSql is) because traceService()/traceExternal()/trace() are
     * called directly by application code with no intermediary that could
     * inject config — CaptureFlowTrace's other config() calls
     * (flow.enabled, flow.sample_rate) are the same established pattern in
     * this package, not a new one.
     */
    private function maskingEnabled(): bool
    {
        return (bool) config('flow.options.mask_sensitive_data', true);
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
