<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchivePlayerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creator_can_archive_player_with_matching_version(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['version' => 2]);
        Sanctum::actingAs($creator);

        $this->deleteJson("/api/v1/players/{$player->id}", ['baseVersion' => 2])->assertNoContent();

        $player->refresh();
        $this->assertNotNull($player->archived_at);
        $this->assertSame(3, $player->version);
        $this->getJson("/api/v1/players/{$player->id}")->assertNotFound();
        $this->getJson("/api/v1/identity/resolve/{$player->player_code}")->assertNotFound();
    }

    public function test_other_user_cannot_archive_player(): void
    {
        $player = Player::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/players/{$player->id}", ['baseVersion' => 1])->assertForbidden();

        $this->assertNull($player->fresh()->archived_at);
    }

    public function test_stale_player_archive_returns_conflict_without_archiving(): void
    {
        $creator = User::factory()->create();
        $player = Player::factory()->for($creator, 'createdByUser')->create(['version' => 4]);
        Sanctum::actingAs($creator);

        $this->deleteJson("/api/v1/players/{$player->id}", ['baseVersion' => 3])
            ->assertConflict()->assertJsonPath('error.code', 'version_conflict')->assertJsonPath('error.conflict.currentVersion', 4);

        $this->assertNull($player->fresh()->archived_at);
    }
}
