<?php

namespace Database\Factories;

use App\Models\CricketMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CricketMatch>
 */
class CricketMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory(),
            'match_type' => 'simple',
            'status' => 'ready',
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'scheduled_at_utc' => fake()->dateTimeBetween('now', '+1 month'),
            'venue' => fake()->streetName(),
            'server_version' => 0,
            'last_sequence' => 0,
            'finalized_at' => null,
            'result_json' => null,
        ];
    }
}
