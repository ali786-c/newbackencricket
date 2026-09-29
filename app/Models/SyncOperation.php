<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'outbox_id',
    'operation',
    'entity_type',
    'entity_id',
    'payload',
    'request_hash',
    'status',
    'accepted_at',
])]
class SyncOperation extends Model
{
    use HasUlids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
