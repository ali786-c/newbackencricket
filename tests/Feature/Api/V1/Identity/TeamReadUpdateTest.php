<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamReadUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_can_read_team_by_canonical_id(): void
    {
        $team = Team::factory()->create();

        $this->getJson("/api/v1/teams/{$team->id}")->assertOk()->assertJsonPath('id', $team->id);
    }

    public function test_authenticated_search_finds_normalized_team_name(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $team = Team::factory()->create(['name' => 'Ali Panthers', 'normalized_name' => 'ali panthers']);

        $this->getJson('/api/v1/teams?query=ALI%20PANTHERS')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $team->id);
    }

    public function test_owner_can_update_team_and_version(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 1]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/teams/{$team->id}", [
            'name' => 'New Team Name', 'shortName' => 'ntn', 'baseVersion' => 1,
        ])->assertOk()->assertJsonPath('shortName', 'NTN')->assertJsonPath('version', 2);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'normalized_name' => 'new team name', 'version' => 2]);
    }

    public function test_non_owner_is_forbidden_from_updating_team(): void
    {
        $team = Team::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/teams/{$team->id}", ['city' => 'Karachi', 'baseVersion' => 1])->assertForbidden();
    }

    public function test_stale_team_update_returns_409_without_changes(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['city' => 'Lahore', 'version' => 3]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/teams/{$team->id}", ['city' => 'Karachi', 'baseVersion' => 2])
            ->assertConflict()->assertJsonPath('error.conflict.currentVersion', 3);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'city' => 'Lahore', 'version' => 3]);
    }
}
