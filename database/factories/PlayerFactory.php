<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'player_code' => 'STP-P-'.fake()->unique()->numerify('########'),
            'claimed_user_id' => null,
            'created_by_user_id' => User::factory(),
            'name' => $name,
            'normalized_name' => str($name)->squish()->lower()->toString(),
            'city' => fake()->city(),
            'playing_role' => fake()->randomElement(['batter', 'bowler', 'all_rounder', 'wicketkeeper']),
            'batting_style' => fake()->randomElement(['right_hand', 'left_hand', 'unknown']),
            'bowling_style' => 'right_arm_medium',
            'bio' => fake()->optional()->sentence(),
            'claim_status' => 'unclaimed',
            'version' => 1,
            'archived_at' => null,
        ];
    }
}
