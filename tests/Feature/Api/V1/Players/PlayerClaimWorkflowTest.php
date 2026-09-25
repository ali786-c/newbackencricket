<?php

namespace Tests\Feature\Api\V1\Players;

use App\Models\Player;
use App\Models\PlayerClaimRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerClaimWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_claim_request_requires_authentication(): void
    {
        $player = Player::factory()->create();

        $this->postJson("/api/v1/players/{$player->id}/claim-requests", ['baseVersion' => 1])->assertUnauthorized();

        $this->assertDatabaseMissing('player_claim_requests', ['player_id' => $player->id]);
    }

    public function test_authenticated_user_can_request_an_unclaimed_player_without_changing_its_identity(): void
    {
        $player = Player::factory()->create(['version' => 1]);
        $claimant = User::factory()->create();
        Sanctum::actingAs($claimant);

        $this->postJson("/api/v1/players/{$player->id}/claim-requests", [
            'baseVersion' => 1,
            'message' => 'This is my cricket profile.',
        ])->assertCreated()
            ->assertJsonPath('playerId', $player->id)
            ->assertJsonPath('claimantUserId', $claimant->id)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('currentPlayerVersion', 2);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'claimed_user_id' => null,
            'claim_status' => 'claim_pending',
            'version' => 2,
        ]);
        $this->assertDatabaseHas('player_claim_requests', [
            'player_id' => $player->id,
            'claimant_user_id' => $claimant->id,
            'status' => 'pending',
        ]);
    }

    public function test_profile_creator_can_approve_claim_and_preserve_player_id(): void
    {
        $creator = User::factory()->create();
        $claimant = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create([
            'claim_status' => 'claim_pending',
            'version' => 2,
        ]);
        $claimRequest = PlayerClaimRequest::factory()->create([
            'player_id' => $player->id,
            'claimant_user_id' => $claimant->id,
            'player_version_at_request' => 1,
        ]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}/claim-requests/{$claimRequest->id}", [
            'decision' => 'approved',
            'baseVersion' => 2,
        ])->assertOk()
            ->assertJsonPath('id', $claimRequest->id)
            ->assertJsonPath('playerId', $player->id)
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('currentPlayerVersion', 3);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'claimed_user_id' => $claimant->id,
            'claim_status' => 'claimed',
            'version' => 3,
        ]);
        $this->assertDatabaseHas('player_claim_requests', [
            'id' => $claimRequest->id,
            'decided_by_user_id' => $creator->id,
            'status' => 'approved',
        ]);
    }

    public function test_non_creator_cannot_decide_a_claim(): void
    {
        $player = Player::factory()->create(['claim_status' => 'claim_pending', 'version' => 2]);
        $claimRequest = PlayerClaimRequest::factory()->create(['player_id' => $player->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/players/{$player->id}/claim-requests/{$claimRequest->id}", [
            'decision' => 'approved',
            'baseVersion' => 2,
        ])->assertForbidden();

        $this->assertDatabaseHas('player_claim_requests', ['id' => $claimRequest->id, 'status' => 'pending']);
        $this->assertNull($player->fresh()->claimed_user_id);
    }

    public function test_claim_decision_rejects_unknown_decision_value(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['claim_status' => 'claim_pending', 'version' => 2]);
        $claimRequest = PlayerClaimRequest::factory()->create(['player_id' => $player->id]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}/claim-requests/{$claimRequest->id}", [
            'decision' => 'transferred',
            'baseVersion' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['decision'], 'error.fieldErrors');

        $this->assertDatabaseHas('player_claim_requests', ['id' => $claimRequest->id, 'status' => 'pending']);
    }

    public function test_creator_can_reject_claim_and_restore_unclaimed_state(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['claim_status' => 'claim_pending', 'version' => 2]);
        $claimRequest = PlayerClaimRequest::factory()->create(['player_id' => $player->id]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}/claim-requests/{$claimRequest->id}", [
            'decision' => 'rejected',
            'reason' => 'Identity could not be verified.',
            'baseVersion' => 2,
        ])->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('currentPlayerVersion', 3);

        $this->assertDatabaseHas('players', ['id' => $player->id, 'claim_status' => 'unclaimed', 'version' => 3]);
    }

    public function test_claimant_with_an_existing_claimed_player_cannot_request_another(): void
    {
        $claimant = User::factory()->create();
        Player::factory()->create(['claimed_user_id' => $claimant->id, 'claim_status' => 'claimed']);
        $target = Player::factory()->create();
        Sanctum::actingAs($claimant);

        $this->postJson("/api/v1/players/{$target->id}/claim-requests", ['baseVersion' => 1])
            ->assertConflict()
            ->assertJsonPath('error.code', 'player_claim_conflict')
            ->assertJsonPath('error.conflict.reason', 'claimant_already_has_player');

        $this->assertDatabaseMissing('player_claim_requests', ['player_id' => $target->id]);
        $this->assertDatabaseHas('players', ['id' => $target->id, 'claim_status' => 'unclaimed', 'version' => 1]);
    }

    public function test_stale_claim_request_returns_version_conflict(): void
    {
        $player = Player::factory()->create(['version' => 3]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/players/{$player->id}/claim-requests", ['baseVersion' => 2])
            ->assertConflict()
            ->assertJsonPath('error.code', 'version_conflict')
            ->assertJsonPath('error.conflict.currentVersion', 3);

        $this->assertDatabaseMissing('player_claim_requests', ['player_id' => $player->id]);
    }
}
