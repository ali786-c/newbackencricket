<?php

namespace App\Models;

use Database\Factories\MatchPlayerSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'source_player_id', 'match_team_snapshot_id', 'name', 'playing_role', 'squad_role'])]
class MatchPlayerSnapshot extends Model
{
    /** @use HasFactory<MatchPlayerSnapshotFactory> */
    use HasFactory, HasUlids;
}
