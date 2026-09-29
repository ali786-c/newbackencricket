<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'user_id', 'device_id', 'state', 'base_server_version', 'acquired_at_utc', 'last_heartbeat_at_utc', 'expires_at_utc', 'released_at_utc'])]
class MatchScoringSession extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['acquired_at_utc' => 'datetime', 'last_heartbeat_at_utc' => 'datetime', 'expires_at_utc' => 'datetime', 'released_at_utc' => 'datetime'];
    }
}
