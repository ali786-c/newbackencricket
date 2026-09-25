<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DuplicateCandidateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_player_candidate_requires_matching_normalized_name_and_city(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $candidate = Player::factory()->create(['name' => 'Ahmed Ali', 'normalized_name' => 'ahmed ali', 'city' => 'Karachi']);
        Player::factory()->create(['name' => 'Ahmed Ali', 'normalized_name' => 'ahmed ali', 'city' => 'Lahore']);

        $this->getJson('/api/v1/identity/duplicate-candidates/player?name=%20AHMED%20%20ALI%20&city=Karachi')
            ->assertOk()->assertJsonPath('status', 'possible_duplicate')->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.canonicalId', $candidate->id)
            ->assertJsonPath('candidates.0.matchedSignals.0', 'normalized_name')
            ->assertJsonMissingPath('candidates.0.claimedUserId');
    }

    public function test_name_alone_is_rejected_as_insufficient_signal(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/identity/duplicate-candidates/player?name=Ahmed%20Ali')
            ->assertUnprocessable()->assertJsonValidationErrors(['city'], 'error.fieldErrors');
    }

    public function test_team_candidates_require_same_owner_in_addition_to_name_and_city(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $candidate = Team::factory()->for($owner, 'owner')->create(['name' => 'Ali Panthers', 'normalized_name' => 'ali panthers', 'city' => 'Karachi']);
        Team::factory()->create(['name' => 'Ali Panthers', 'normalized_name' => 'ali panthers', 'city' => 'Karachi']);

        $this->getJson('/api/v1/identity/duplicate-candidates/team?name=Ali%20Panthers&city=Karachi')
            ->assertOk()->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.canonicalId', $candidate->id)
            ->assertJsonPath('candidates.0.matchedSignals.2', 'owner')->assertJsonMissingPath('candidates.0.ownerUserId');
    }

    public function test_candidate_can_exclude_current_canonical_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $player = Player::factory()->create(['name' => 'Ahmed Ali', 'normalized_name' => 'ahmed ali', 'city' => 'Karachi']);

        $this->getJson("/api/v1/identity/duplicate-candidates/player?name=Ahmed%20Ali&city=Karachi&excludeId={$player->id}")
            ->assertOk()->assertJsonPath('status', 'no_candidates')->assertJsonCount(0, 'candidates');
    }

    public function test_duplicate_candidate_lookup_requires_authentication(): void
    {
        $this->getJson('/api/v1/identity/duplicate-candidates/player?name=Ahmed%20Ali&city=Karachi')->assertUnauthorized();
    }

    public function test_invalid_entity_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/identity/duplicate-candidates/match?name=Ahmed%20Ali&city=Karachi')
            ->assertUnprocessable()->assertJsonValidationErrors(['entityType'], 'error.fieldErrors');
    }
}
