<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Auto-detects outbound HTTP calls made via the Http:: facade / HTTP client,
 * mirroring how Laravel Debugbar's HttpClientCollector works: no manual
 * Archon::trace('external', ...) call is required in application code.
 */
final class ExternalHttpHook
{
    private bool $registered = false;

    /** @var array<int, int> spl_object_id(Request) => start time (ms) */
    private array $startedAt = [];

    public function __construct(
        private readonly TraceCollector $collector,
    ) {}

    public function register(Dispatcher $events): void
    {
        if ($this->registered) {
            return;
        }

        $events->listen(RequestSending::class, function (RequestSending $event): void {
            $this->startedAt[spl_object_id($event->request)] = $this->nowMs();
        });

        $events->listen(ResponseReceived::class, function (ResponseReceived $event): void {
            $durationMs = $this->resolveDuration($event->request, $event->response->transferStats?->getTransferTime());

            $this->collector->recordExternalCall(
                label: $this->formatLabel($event->request->method(), $event->request->url()),
                durationMs: $durationMs,
                meta: [
                    'method' => $event->request->method(),
                    'url' => $event->request->url(),
                    'status' => $event->response->status(),
                ],
            );
        });

        $events->listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            $durationMs = $this->resolveDuration($event->request, null);

            $this->collector->recordExternalCall(
                label: $this->formatLabel($event->request->method(), $event->request->url()),
                durationMs: $durationMs,
                meta: [
                    'method' => $event->request->method(),
                    'url' => $event->request->url(),
                    'status' => 'failed',
                ],
            );
        });

        $this->registered = true;
    }

    private function resolveDuration(mixed $request, ?float $transferTimeSeconds): int
    {
        if ($transferTimeSeconds !== null) {
            return max(1, (int) round($transferTimeSeconds * 1000));
        }

        $key = spl_object_id($request);
        $startedAt = $this->startedAt[$key] ?? null;
        unset($this->startedAt[$key]);

        if ($startedAt === null) {
            return 1;
        }

        return max(1, $this->nowMs() - $startedAt);
    }

    private function formatLabel(string $method, string $url): string
    {
        $label = strtoupper($method) . ' ' . $url;

        return strlen($label) <= 80 ? $label : substr($label, 0, 77) . '...';
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
