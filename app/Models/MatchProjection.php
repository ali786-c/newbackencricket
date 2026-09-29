<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'source_sequence', 'server_version', 'schema_version', 'payload_json'])]
class MatchProjection extends Model
{
    protected $primaryKey = 'match_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
    }
}
