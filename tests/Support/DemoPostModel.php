<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A separate fixture from NoteModel specifically for
 * RouteDiscovery::describeRelationships() — only the declared method
 * signature matters (reflection-only, never queried), so the underlying
 * table/columns don't need to make real-world sense.
 */
class DemoPostModel extends Model
{
    protected $table = 'notes';

    public function comments(): HasMany
    {
        return $this->hasMany(NoteModel::class);
    }
}
