<?php

namespace App\Models;

use Database\Factories\EntityAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entity_type', 'retired_id', 'surviving_id', 'actor_user_id', 'reason'])]
class EntityAlias extends Model
{
    /** @use HasFactory<EntityAliasFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
