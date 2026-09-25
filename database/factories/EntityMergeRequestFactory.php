<?php

namespace Database\Factories;

use App\Models\EntityMergeRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityMergeRequest>
 */
class EntityMergeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'player',
            'retired_id' => Player::factory(),
            'surviving_id' => Player::factory(),
            'requested_by_user_id' => User::factory(),
            'decided_by_user_id' => null,
            'status' => 'pending',
            'reason' => 'Confirmed duplicate identity',
            'retired_base_version' => 1,
            'surviving_base_version' => 1,
            'requested_at' => now('UTC'),
            'decided_at' => null,
        ];
    }
}
