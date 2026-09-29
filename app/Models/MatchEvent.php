<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'match_id', 'innings_id', 'scoring_session_id', 'device_id', 'sequence', 'event_type', 'event_schema_version', 'rule_profile_version', 'base_server_version', 'occurred_at_utc', 'payload_json', 'supersedes_event_id'])]
class MatchEvent extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['payload_json' => 'array', 'occurred_at_utc' => 'datetime', 'sequence' => 'integer'];
    }
}
