<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Transport;

/**
 * Lets CaptureFlowTrace hand off a captured record without knowing how (or
 * whether) it leaves this process — Connected mode pushes it to Flow API,
 * Offline/CLI mode relies purely on the local flow-history.json file
 * TraceWriter already writes unconditionally (see FlowServiceProvider's
 * singleton binding for which implementation gets used).
 */
interface TraceTransport
{
    /** @param array<string, mixed> $flowRecord */
    public function send(array $flowRecord): void;
}
