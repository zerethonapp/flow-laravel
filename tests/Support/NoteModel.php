<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Falls under the same trace_namespaces scan as the other test fixtures —
 * proves TracingProxyFactory refuses to proxy Eloquent models even when
 * they're otherwise eligible, because a proxy subclass breaks late static
 * binding (static::class) that Eloquent relies on internally.
 */
class NoteModel extends Model
{
    protected $table = 'notes';
}
