<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerClaimRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerClaimRequest>
 */
class PlayerClaimRequestFactory extends Factory
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
            'claimant_user_id' => User::factory(),
            'decided_by_user_id' => null,
            'status' => 'pending',
            'request_message' => fake()->optional()->sentence(),
            'decision_reason' => null,
            'player_version_at_request' => 1,
            'requested_at' => now('UTC'),
            'decided_at' => null,
        ];
    }
}
