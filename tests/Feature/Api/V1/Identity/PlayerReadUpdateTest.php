<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerReadUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_can_read_player_by_canonical_id(): void
    {
        $player = Player::factory()->create();

        $this->getJson("/api/v1/players/{$player->id}")
            ->assertOk()->assertJsonPath('id', $player->id)->assertJsonPath('playerCode', $player->player_code);
    }

    public function test_authenticated_search_resolves_code_case_insensitively(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $player = Player::factory()->create(['player_code' => 'STP-P-7K4M9Q2D']);
        Player::factory()->create();

        $this->getJson('/api/v1/players?code=stp-p-7k4m9q2d')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $player->id);
    }

    public function test_player_search_rejects_a_team_code(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/players?code=STP-T-7K4M9Q2D')
            ->assertUnprocessable()->assertJsonValidationErrors(['code'], 'error.fieldErrors');
    }

    public function test_creator_can_update_player_with_matching_version(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['version' => 1]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}", ['name' => ' Updated  Name ', 'baseVersion' => 1])
            ->assertOk()->assertJsonPath('name', 'Updated Name')->assertJsonPath('version', 2);

        $this->assertDatabaseHas('players', ['id' => $player->id, 'normalized_name' => 'updated name', 'version' => 2]);
    }

    public function test_other_user_is_forbidden_from_updating_player(): void
    {
        $player = Player::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/players/{$player->id}", ['name' => 'Denied', 'baseVersion' => 1])->assertForbidden();
    }

    public function test_stale_player_update_returns_409_without_changes(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['name' => 'Original', 'version' => 2]);
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}", ['name' => 'Stale', 'baseVersion' => 1])
            ->assertConflict()->assertJsonPath('error.code', 'version_conflict')->assertJsonPath('error.conflict.currentVersion', 2);

        $this->assertDatabaseHas('players', ['id' => $player->id, 'name' => 'Original', 'version' => 2]);
    }

    public function test_player_update_rejects_base_version_without_an_edit(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create();
        Sanctum::actingAs($creator);

        $this->patchJson("/api/v1/players/{$player->id}", ['baseVersion' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['request'], 'error.fieldErrors');
    }
}
