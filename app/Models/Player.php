<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['player_code', 'claimed_user_id', 'created_by_user_id', 'name', 'normalized_name', 'city', 'playing_role', 'batting_style', 'bowling_style', 'bio', 'claim_status', 'version', 'archived_at'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, HasUlids;

    public function claimedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function claimRequests(): HasMany
    {
        return $this->hasMany(PlayerClaimRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'version' => 'integer'];
    }
}
