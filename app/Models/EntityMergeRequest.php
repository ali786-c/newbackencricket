<?php

namespace App\Models;

use Database\Factories\EntityMergeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entity_type', 'retired_id', 'surviving_id', 'requested_by_user_id', 'decided_by_user_id', 'status', 'reason', 'retired_base_version', 'surviving_base_version', 'requested_at', 'decided_at'])]
class EntityMergeRequest extends Model
{
    /** @use HasFactory<EntityMergeRequestFactory> */
    use HasFactory, HasUlids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_base_version' => 'integer', 'surviving_base_version' => 'integer', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
