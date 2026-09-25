<?php

namespace App\Models;

use Database\Factories\TeamOwnershipTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'from_user_id', 'to_user_id', 'decided_by_user_id', 'status', 'message', 'decision_reason', 'team_version_at_request', 'requested_at', 'decided_at'])]
class TeamOwnershipTransfer extends Model
{
    /** @use HasFactory<TeamOwnershipTransferFactory> */
    use HasFactory, HasUlids;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['team_version_at_request' => 'integer', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
