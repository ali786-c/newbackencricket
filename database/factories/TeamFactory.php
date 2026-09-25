<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'team_code' => 'STP-T-'.fake()->unique()->numerify('########'),
            'owner_user_id' => User::factory(),
            'name' => $name,
            'normalized_name' => str($name)->squish()->lower()->toString(),
            'short_name' => str(fake()->unique()->lexify('???'))->upper()->toString(),
            'city' => fake()->city(),
            'description' => fake()->optional()->sentence(),
            'version' => 1,
            'archived_at' => null,
        ];
    }
}
