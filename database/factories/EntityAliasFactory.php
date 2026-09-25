<?php

namespace Database\Factories;

use App\Models\EntityAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntityAlias>
 */
class EntityAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => fake()->randomElement(['player', 'team']),
            'retired_id' => (string) Str::ulid(),
            'surviving_id' => (string) Str::ulid(),
            'actor_user_id' => User::factory(),
            'reason' => fake()->sentence(),
        ];
    }
}
