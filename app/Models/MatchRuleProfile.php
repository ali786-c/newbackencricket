<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'version', 'overs_per_innings', 'balls_per_over', 'players_per_side', 'wickets_per_innings', 'ball_type'])]
class MatchRuleProfile extends Model
{
    public $incrementing = false;
}
