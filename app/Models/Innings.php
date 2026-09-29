<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'innings_number', 'status', 'batting_team_snapshot_id', 'bowling_team_snapshot_id', 'started_at_utc', 'completed_at_utc'])]
class Innings extends Model
{
    use HasUlids;
}
