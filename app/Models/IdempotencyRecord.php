<?php

namespace App\Models;

use Database\Factories\IdempotencyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'operation', 'idempotency_key', 'request_hash', 'resource_type', 'resource_id', 'response_status'])]
class IdempotencyRecord extends Model
{
    /** @use HasFactory<IdempotencyRecordFactory> */
    use HasFactory, HasUlids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
