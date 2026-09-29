<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'match_updates' => 'boolean', 'tournament_updates' => 'boolean',
            'team_updates' => 'boolean', 'system_updates' => 'boolean',
            'marketing' => 'boolean',
        ];
    }
}
