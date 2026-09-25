<?php

namespace Tests\Feature\Api\V1\Teams;

use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_membership_with_client_id(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $player = Player::factory()->make();
        $player->id = (string) Str::ulid();
        $player->save();
        $membershipId = (string) Str::ulid();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/memberships", [
            'id' => $membershipId,
            'playerId' => $player->id,
            'joinedAtUtc' => '2026-09-24T10:00:00Z',
            'teamRole' => 'captain',
        ])->assertCreated()->assertJsonPath('id', $membershipId)->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('team_memberships', [
            'id' => $membershipId, 'team_id' => $team->id, 'player_id' => $player->id,
        ]);
    }

    public function test_non_owner_cannot_create_membership(): void
    {
        $team = Team::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/teams/{$team->id}/memberships", [
            'id' => (string) Str::ulid(), 'playerId' => Player::factory()->create()->id,
            'joinedAtUtc' => '2026-09-24T10:00:00Z',
        ])->assertForbidden();
    }

    public function test_owner_can_end_membership_and_replay_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $membership = TeamMembership::factory()->for($team)->create(['joined_at' => '2026-01-01 00:00:00']);
        Sanctum::actingAs($owner);
        $url = "/api/v1/teams/{$team->id}/memberships/{$membership->id}";

        $this->patchJson($url, ['leftAtUtc' => '2026-09-24T12:00:00Z'])
            ->assertOk()->assertJsonPath('status', 'past')->assertJsonPath('version', 2);
        $this->patchJson($url, ['leftAtUtc' => '2026-09-24T12:00:00Z'])
            ->assertOk()->assertJsonPath('version', 2);

        $this->assertDatabaseHas('team_memberships', ['id' => $membership->id, 'status' => 'past', 'version' => 2]);
    }

    public function test_membership_from_another_team_returns_404(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $otherMembership = TeamMembership::factory()->create();
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/teams/{$team->id}/memberships/{$otherMembership->id}", [
            'leftAtUtc' => '2026-09-24T12:00:00Z',
        ])->assertNotFound();
    }
}
