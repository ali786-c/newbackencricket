<?php

namespace Database\Factories;

use App\Models\MatchTeamSnapshot;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MatchTeamSnapshot>
 */
class MatchTeamSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => (string) Str::ulid(),
            'source_team_id' => Team::factory(),
            'name' => fake()->company(),
            'short_name' => 'TST',
        ];
    }
}
