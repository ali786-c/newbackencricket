<?php

namespace Tests\Feature\Api\V1\Tournaments;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_rejects_unregistered_team_and_inherits_rule_version(): void
    {
        [$owner, $tournament, $home, $away] = $this->context();
        Sanctum::actingAs($owner);
        $outside = Team::factory()->for($owner, 'owner')->create();
        $payload = $this->fixturePayload($home, $away);
        $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures", [...$payload, 'awayTournamentTeamId' => (string) Str::ulid()])->assertUnprocessable();
        $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures", $payload)->assertCreated()->assertJsonPath('data.ruleProfileVersion', 3);
    }

    public function test_confirmation_is_rebuilt_exactly_once_and_correction_recalculates_nrr(): void
    {
        [$owner, $tournament, $home, $away] = $this->context();
        Sanctum::actingAs($owner);
        $fixture = $this->fixturePayload($home, $away);
        $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures", $fixture)->assertCreated();
        $result = ['id' => (string) Str::ulid(), 'outcome' => 'home_win', 'homeRuns' => 120, 'homeLegalBalls' => 120, 'awayRuns' => 90, 'awayLegalBalls' => 120];
        $url = "/api/v1/tournaments/{$tournament->id}/fixtures/{$fixture['id']}/confirm-result";
        $this->postJson($url, $result)->assertOk();
        $this->postJson($url, $result)->assertOk();
        $this->assertSame(1, DB::table('tournament_standings')->where('tournament_team_id', $home)->value('played'));
        $this->assertSame(2, DB::table('tournament_standings')->where('tournament_team_id', $home)->value('points'));
        $this->assertEquals(1.5, DB::table('tournament_standings')->where('tournament_team_id', $home)->value('net_run_rate'));
        $this->postJson($url, [...$result, 'id' => (string) Str::ulid(), 'homeRuns' => 100])->assertOk();
        $this->assertEquals(0.5, DB::table('tournament_standings')->where('tournament_team_id', $home)->value('net_run_rate'));
    }

    public function test_authorized_start_creates_tournament_match_with_inherited_rules_once(): void
    {
        [$owner, $tournament, $home, $away] = $this->context();
        Sanctum::actingAs($owner);
        $fixture = $this->fixturePayload($home, $away);
        $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures", $fixture)->assertCreated();
        $response = $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures/{$fixture['id']}/start")->assertOk();
        $matchId = $response->json('data.matchId');
        $this->assertDatabaseHas('matches', ['id' => $matchId, 'match_type' => 'tournament']);
        $this->assertDatabaseHas('match_rule_profiles', ['match_id' => $matchId, 'version' => 3, 'overs_per_innings' => 20]);
        $this->postJson("/api/v1/tournaments/{$tournament->id}/fixtures/{$fixture['id']}/start")->assertConflict();
    }

    /** @return array{User,Tournament,string,string} */
    private function context(): array
    {
        $owner = User::factory()->create();
        $tournament = new Tournament(['owner_user_id' => $owner->id, 'name' => 'Cup', 'normalized_name' => 'cup', 'city' => 'Lahore', 'season' => '2026', 'status' => 'live', 'starts_at' => now(), 'ends_at' => now()->addDays(5), 'rule_profile_version' => 3, 'overs_per_innings' => 20, 'balls_per_over' => 6, 'players_per_side' => 11, 'wickets_per_innings' => 10, 'ball_type' => 'leather', 'points_for_win' => 2, 'points_for_tie' => 1, 'points_for_no_result' => 1, 'version' => 1]);
        $tournament->id = (string) Str::ulid();
        $tournament->save();
        $ids = [];
        foreach ([Team::factory()->for($owner, 'owner')->create(), Team::factory()->for($owner, 'owner')->create()] as $team) {
            $id = (string) Str::ulid();
            DB::table('tournament_teams')->insert(['id' => $id, 'tournament_id' => $tournament->id, 'team_id' => $team->id, 'status' => 'accepted', 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tournament_standings')->insert(['tournament_id' => $tournament->id, 'tournament_team_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            $ids[] = $id;
        }

        return [$owner, $tournament, $ids[0], $ids[1]];
    }

    /** @return array<string,mixed> */
    private function fixturePayload(string $home, string $away): array
    {
        return ['id' => (string) Str::ulid(), 'homeTournamentTeamId' => $home, 'awayTournamentTeamId' => $away, 'stage' => 'league', 'groupName' => 'A', 'roundNumber' => 1, 'scheduledAtUtc' => now()->addDay()->toIso8601String(), 'venue' => 'City Ground'];
    }
}
