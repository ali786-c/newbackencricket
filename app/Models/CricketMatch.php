<?php

namespace App\Models;

use Database\Factories\CricketMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by_user_id', 'match_type', 'status', 'home_team_id', 'away_team_id', 'scheduled_at_utc', 'venue', 'server_version', 'last_sequence', 'finalized_at', 'result_json'])]
class CricketMatch extends Model
{
    /** @use HasFactory<CricketMatchFactory> */
    use HasFactory, HasUlids;

    protected $table = 'matches';

    protected function casts(): array
    {
        return ['scheduled_at_utc' => 'datetime', 'finalized_at' => 'datetime', 'result_json' => 'array', 'server_version' => 'integer', 'last_sequence' => 'integer'];
    }
}
