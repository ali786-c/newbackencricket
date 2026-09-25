<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'status' => 'active',
            'team_role' => null,
            'joined_at' => now(),
            'left_at' => null,
            'version' => 1,
        ];
    }
}
