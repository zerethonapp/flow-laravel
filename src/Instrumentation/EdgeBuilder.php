<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation;

final class EdgeBuilder
{
    /**
     * @return array{from: string, to: string, type: string}
     */
    public function make(string $from, string $to, string $type = "call"): array
    {
        return [
            "from" => $from,
            "to" => $to,
            "type" => $type,
        ];
    }
}
