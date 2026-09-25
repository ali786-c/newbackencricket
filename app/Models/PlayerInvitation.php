<?php

namespace App\Models;

use Database\Factories\PlayerInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['player_id', 'created_by_user_id', 'contact_type', 'encrypted_contact_value', 'status'])]
class PlayerInvitation extends Model
{
    /** @use HasFactory<PlayerInvitationFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return ['encrypted_contact_value' => 'encrypted'];
    }
}
