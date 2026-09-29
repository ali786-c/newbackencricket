<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_id', 'target_event_id', 'correction_event_id', 'created_by_user_id', 'reason'])]
class EventCorrection extends Model
{
    use HasUlids;
}
