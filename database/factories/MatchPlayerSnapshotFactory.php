<?php

namespace Database\Factories;

use App\Models\MatchPlayerSnapshot;
use App\Models\MatchTeamSnapshot;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MatchPlayerSnapshot>
 */
class MatchPlayerSnapshotFactory extends Factory
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
            'source_player_id' => Player::factory(),
            'match_team_snapshot_id' => MatchTeamSnapshot::factory(),
            'name' => fake()->name(),
            'playing_role' => 'batter',
            'squad_role' => null,
        ];
    }
}
