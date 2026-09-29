<?php

namespace Tests\Feature\Api\V1\Teams;

use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListOwnedTeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mine_scope_returns_only_teams_owned_by_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Team::factory()->for($owner, 'owner')->create(['name' => 'My Falcons', 'normalized_name' => 'my falcons']);
        Team::factory()->for($other, 'owner')->create(['name' => 'Other Falcons', 'normalized_name' => 'other falcons']);
        $claimedPlayer = Player::factory()->create(['claimed_user_id' => $owner->id, 'claim_status' => 'claimed']);
        $memberTeam = Team::factory()->for($other, 'owner')->create(['name' => 'Member Stars', 'normalized_name' => 'member stars']);
        TeamMembership::factory()->for($memberTeam)->for($claimedPlayer)->create(['status' => 'active', 'left_at' => null]);
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/teams?scope=mine')->assertOk();

        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Member Stars', 'My Falcons'], $names);
    }
}
