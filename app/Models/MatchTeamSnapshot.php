<?php

namespace App\Models;

use Database\Factories\MatchTeamSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'source_team_id', 'name', 'short_name'])]
class MatchTeamSnapshot extends Model
{
    /** @use HasFactory<MatchTeamSnapshotFactory> */
    use HasFactory, HasUlids;
}
