<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\TraceCollector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

final class DatabaseHook
{
    private bool $registered = false;

    public function __construct(
        private readonly TraceCollector $collector,
    ) {}

    public function register(bool $captureSql = false): void
    {
        if ($this->registered) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($captureSql): void {
            $this->collector->recordDatabaseQuery($query, $captureSql);
        });

        $this->registered = true;
    }
}
