<?php

namespace Tests\Feature\Api\V1\Teams;

use App\Models\Team;
use App\Models\TeamOwnershipTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamOwnershipTransferWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_offer_transfer_without_changing_team_identity_or_owner(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 2]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/ownership-transfers", [
            'toUserId' => $recipient->id,
            'baseVersion' => 2,
        ])->assertCreated()->assertJsonPath('teamId', $team->id)->assertJsonPath('status', 'pending')->assertJsonPath('currentTeamVersion', 2);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'owner_user_id' => $owner->id, 'version' => 2]);
        $this->assertDatabaseHas('team_ownership_transfers', ['team_id' => $team->id, 'from_user_id' => $owner->id, 'to_user_id' => $recipient->id, 'status' => 'pending']);
    }

    public function test_target_user_can_accept_transfer_and_preserve_team_id(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 1]);
        $transfer = TeamOwnershipTransfer::factory()->create(['team_id' => $team->id, 'from_user_id' => $owner->id, 'to_user_id' => $recipient->id]);
        Sanctum::actingAs($recipient);

        $this->patchJson("/api/v1/teams/{$team->id}/ownership-transfers/{$transfer->id}", ['decision' => 'accepted', 'baseVersion' => 1])
            ->assertOk()->assertJsonPath('teamId', $team->id)->assertJsonPath('status', 'accepted')->assertJsonPath('currentTeamVersion', 2);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'owner_user_id' => $recipient->id, 'version' => 2]);
        $this->assertDatabaseHas('team_ownership_transfers', ['id' => $transfer->id, 'decided_by_user_id' => $recipient->id, 'status' => 'accepted']);
    }

    public function test_non_target_user_cannot_decide_transfer(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $transfer = TeamOwnershipTransfer::factory()->create(['team_id' => $team->id, 'from_user_id' => $owner->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/teams/{$team->id}/ownership-transfers/{$transfer->id}", ['decision' => 'accepted', 'baseVersion' => 1])->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'owner_user_id' => $owner->id]);
    }

    public function test_target_can_reject_without_changing_team_version(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 3]);
        $transfer = TeamOwnershipTransfer::factory()->create(['team_id' => $team->id, 'from_user_id' => $owner->id, 'to_user_id' => $recipient->id, 'team_version_at_request' => 3]);
        Sanctum::actingAs($recipient);

        $this->patchJson("/api/v1/teams/{$team->id}/ownership-transfers/{$transfer->id}", ['decision' => 'rejected', 'baseVersion' => 3])
            ->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('currentTeamVersion', 3);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'owner_user_id' => $owner->id, 'version' => 3]);
    }

    public function test_owner_cannot_offer_transfer_to_self(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/ownership-transfers", ['toUserId' => $owner->id, 'baseVersion' => 1])
            ->assertConflict()->assertJsonPath('error.conflict.reason', 'recipient_is_current_owner');

        $this->assertDatabaseMissing('team_ownership_transfers', ['team_id' => $team->id]);
    }

    public function test_stale_transfer_offer_returns_version_conflict(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 4]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/teams/{$team->id}/ownership-transfers", ['toUserId' => User::factory()->create()->id, 'baseVersion' => 3])
            ->assertConflict()->assertJsonPath('error.code', 'version_conflict');

        $this->assertDatabaseMissing('team_ownership_transfers', ['team_id' => $team->id]);
    }
}
