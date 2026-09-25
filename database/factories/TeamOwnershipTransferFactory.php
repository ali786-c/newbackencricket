<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamOwnershipTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamOwnershipTransfer>
 */
class TeamOwnershipTransferFactory extends Factory
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
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'decided_by_user_id' => null,
            'status' => 'pending',
            'message' => null,
            'decision_reason' => null,
            'team_version_at_request' => 1,
            'requested_at' => now('UTC'),
            'decided_at' => null,
        ];
    }
}
