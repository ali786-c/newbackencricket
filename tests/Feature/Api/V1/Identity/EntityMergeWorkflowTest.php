<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\EntityMergeRequest;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntityMergeWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_can_request_and_execute_player_merge(): void
    {
        $actor = User::factory()->create();
        $retired = Player::factory()->for($actor, 'createdByUser')->create();
        $survivor = Player::factory()->for($actor, 'createdByUser')->create();
        $team = Team::factory()->create();
        $membership = TeamMembership::factory()->create(['team_id' => $team->id, 'player_id' => $retired->id]);
        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/identity/merge-requests', [
            'entityType' => 'player', 'retiredId' => $retired->id, 'survivingId' => $survivor->id,
            'retiredBaseVersion' => 1, 'survivingBaseVersion' => 1, 'reason' => 'Confirmed duplicate profile',
        ])->assertCreated()->assertJsonPath('status', 'pending');
        $mergeId = $response->json('id');

        $this->patchJson("/api/v1/identity/merge-requests/{$mergeId}", ['decision' => 'execute'])
            ->assertOk()->assertJsonPath('status', 'executed')->assertJsonPath('survivingId', $survivor->id);

        $this->assertDatabaseHas('entity_aliases', ['entity_type' => 'player', 'retired_id' => $retired->id, 'surviving_id' => $survivor->id]);
        $this->assertDatabaseHas('team_memberships', ['id' => $membership->id, 'player_id' => $survivor->id]);
        $this->assertNotNull($retired->fresh()->archived_at);
        $this->getJson("/api/v1/identity/resolve/{$retired->player_code}")->assertJsonPath('canonicalId', $survivor->id);
    }

    public function test_user_must_control_both_entities_to_request_merge(): void
    {
        $actor = User::factory()->create();
        $retired = Player::factory()->for($actor, 'createdByUser')->create();
        $survivor = Player::factory()->create();
        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/identity/merge-requests', [
            'entityType' => 'player', 'retiredId' => $retired->id, 'survivingId' => $survivor->id,
            'retiredBaseVersion' => 1, 'survivingBaseVersion' => 1, 'reason' => 'Not authorized',
        ])->assertConflict()->assertJsonPath('error.conflict.reason', 'entities_missing_or_unauthorized');

        $this->assertDatabaseCount('entity_merge_requests', 0);
    }

    public function test_membership_collision_blocks_merge_without_partial_changes(): void
    {
        $actor = User::factory()->create();
        $retired = Player::factory()->for($actor, 'createdByUser')->create();
        $survivor = Player::factory()->for($actor, 'createdByUser')->create();
        $team = Team::factory()->create();
        TeamMembership::factory()->create(['team_id' => $team->id, 'player_id' => $retired->id]);
        TeamMembership::factory()->create(['team_id' => $team->id, 'player_id' => $survivor->id]);
        $merge = EntityMergeRequest::factory()->create(['entity_type' => 'player', 'retired_id' => $retired->id, 'surviving_id' => $survivor->id, 'requested_by_user_id' => $actor->id]);
        Sanctum::actingAs($actor);

        $this->patchJson("/api/v1/identity/merge-requests/{$merge->id}", ['decision' => 'execute'])
            ->assertConflict()->assertJsonPath('error.conflict.reason', 'membership_overlap_requires_manual_resolution');

        $this->assertDatabaseMissing('entity_aliases', ['retired_id' => $retired->id]);
        $this->assertNull($retired->fresh()->archived_at);
    }
}
