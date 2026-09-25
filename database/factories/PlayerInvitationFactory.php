<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerInvitation>
 */
class PlayerInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'created_by_user_id' => User::factory(),
            'contact_type' => 'phone',
            'encrypted_contact_value' => '+923001234567',
            'status' => 'pending',
        ];
    }
}
