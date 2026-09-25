<?php

namespace Tests\Feature\Api\V1\Identity;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchiveTeamTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_archive_team_with_matching_version(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 1]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/teams/{$team->id}", ['baseVersion' => 1])->assertNoContent();

        $team->refresh();
        $this->assertNotNull($team->archived_at);
        $this->assertSame(2, $team->version);
        $this->getJson("/api/v1/teams/{$team->id}")->assertNotFound();
        $this->getJson("/api/v1/identity/resolve/{$team->team_code}")->assertNotFound();
    }

    public function test_non_owner_cannot_archive_team(): void
    {
        $team = Team::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/teams/{$team->id}", ['baseVersion' => 1])->assertForbidden();

        $this->assertNull($team->fresh()->archived_at);
    }

    public function test_stale_team_archive_returns_conflict_without_archiving(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['version' => 5]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/teams/{$team->id}", ['baseVersion' => 4])
            ->assertConflict()->assertJsonPath('error.code', 'version_conflict')->assertJsonPath('error.conflict.currentVersion', 5);

        $this->assertNull($team->fresh()->archived_at);
    }
}
