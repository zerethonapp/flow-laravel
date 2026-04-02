<?php

declare(strict_types=1);

namespace ArchonFlow\Laravel\Instrumentation\Hooks;

use ArchonFlow\Laravel\Instrumentation\InstrumentationManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

final class DatabaseHook
{
    private bool $registered = false;

    public function __construct(
        private readonly InstrumentationManager $manager,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $this->manager->recordDatabaseQuery($query);
        });

        $this->registered = true;
    }
}
