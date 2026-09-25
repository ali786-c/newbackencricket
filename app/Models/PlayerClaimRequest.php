<?php

namespace App\Models;

use Database\Factories\PlayerClaimRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['player_id', 'claimant_user_id', 'decided_by_user_id', 'status', 'request_message', 'decision_reason', 'player_version_at_request', 'requested_at', 'decided_at'])]
class PlayerClaimRequest extends Model
{
    /** @use HasFactory<PlayerClaimRequestFactory> */
    use HasFactory, HasUlids;

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'player_version_at_request' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
