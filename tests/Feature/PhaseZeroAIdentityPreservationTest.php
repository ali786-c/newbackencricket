<?php

namespace Tests\Feature;

use App\Domains\Identity\Actions\DecideEntityMergeAction;
use App\Domains\Players\Actions\UpdatePlayerAction;
use App\Domains\Teams\Actions\ArchiveTeamAction;
use App\Models\EntityMergeRequest;
use App\Models\MatchPlayerSnapshot;
use App\Models\MatchTeamSnapshot;
use App\Models\Player;
use App\Models\PlayerInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseZeroAIdentityPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_invitation_contact_is_encrypted_and_never_exposed_publicly(): void
    {
        $player = Player::factory()->make();
        $player->id = (string) Str::ulid();
        $player->save();
        $invitation = PlayerInvitation::factory()->create(['player_id' => $player->id]);

        $this->assertNotSame('+923001234567', $invitation->getRawOriginal('encrypted_contact_value'));
        $this->getJson("/api/v1/players/{$player->id}")
            ->assertOk()
            ->assertJsonMissing(['encrypted_contact_value' => '+923001234567'])
            ->assertJsonMissingPath('contact');
    }

    public function test_rename_and_archive_do_not_change_match_snapshots_or_source_ids(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create(['name' => 'Original Team']);
        $player = Player::factory()->for($owner, 'createdByUser')->create(['name' => 'Original Player']);
        $teamSnapshot = MatchTeamSnapshot::factory()->create(['source_team_id' => $team->id, 'name' => 'Original Team']);
        $playerSnapshot = MatchPlayerSnapshot::factory()->create([
            'match_id' => $teamSnapshot->match_id,
            'source_player_id' => $player->id,
            'match_team_snapshot_id' => $teamSnapshot->id,
            'name' => 'Original Player',
        ]);

        (new UpdatePlayerAction)->handle($player, ['name' => 'Renamed Player', 'baseVersion' => 1]);
        (new ArchiveTeamAction)->handle($team, 1);

        $this->assertSame('Original Team', $teamSnapshot->fresh()->name);
        $this->assertSame($team->id, $teamSnapshot->fresh()->source_team_id);
        $this->assertSame('Original Player', $playerSnapshot->fresh()->name);
        $this->assertSame($player->id, $playerSnapshot->fresh()->source_player_id);
        $this->assertDatabaseHas('players', ['id' => $player->id, 'name' => 'Renamed Player']);
        $this->assertNotNull($team->fresh()->archived_at);
    }

    public function test_squad_leadership_is_membership_metadata_not_player_identity(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->for($owner, 'owner')->create();
        $player = Player::factory()->make();
        $player->id = (string) Str::ulid();
        $player->save();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/teams/{$team->id}/memberships", [
            'id' => (string) Str::ulid(),
            'playerId' => $player->id,
            'joinedAtUtc' => '2026-09-25T00:00:00Z',
            'teamRole' => 'captain',
        ])->assertCreated()->assertJsonPath('teamRole', 'captain');

        $this->assertArrayNotHasKey('team_role', $player->fresh()->getAttributes());
    }

    public function test_merge_preserves_completed_snapshot_and_creates_future_navigation_alias(): void
    {
        $actor = User::factory()->create();
        $retired = Player::factory()->for($actor, 'createdByUser')->create();
        $surviving = Player::factory()->for($actor, 'createdByUser')->create();
        $snapshot = MatchPlayerSnapshot::factory()->create(['source_player_id' => $retired->id, 'name' => 'Historic Name']);
        $merge = EntityMergeRequest::factory()->create([
            'retired_id' => $retired->id,
            'surviving_id' => $surviving->id,
            'requested_by_user_id' => $actor->id,
        ]);

        app(DecideEntityMergeAction::class)->handle($merge, $actor, 'execute');

        $this->assertSame('Historic Name', $snapshot->fresh()->name);
        $this->assertSame($retired->id, $snapshot->fresh()->source_player_id);
        $this->assertDatabaseHas('entity_aliases', ['entity_type' => 'player', 'retired_id' => $retired->id, 'surviving_id' => $surviving->id]);
    }
}
