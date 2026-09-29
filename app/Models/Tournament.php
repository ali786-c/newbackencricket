<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_user_id', 'name', 'normalized_name', 'city', 'season', 'status', 'starts_at', 'ends_at', 'description', 'logo_path', 'rule_profile_version', 'overs_per_innings', 'balls_per_over', 'players_per_side', 'wickets_per_innings', 'ball_type', 'points_for_win', 'points_for_tie', 'points_for_no_result', 'version'])]
class Tournament extends Model
{
    use HasFactory, HasUlids;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rule_profile_version' => 'integer',
            'version' => 'integer',
        ];
    }
}
