<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matches\Actions\CreateMatchAction;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TournamentHubController extends Controller
{
    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeViewer($request, $tournament);
        $teams = DB::table('tournament_teams as registration')->join('teams as team', 'team.id', '=', 'registration.team_id')
            ->where('registration.tournament_id', $tournament->id)->orderBy('team.normalized_name')
            ->get(['registration.id', 'registration.status', 'registration.seed', 'team.id as teamId', 'team.team_code as teamCode', 'team.owner_user_id as ownerUserId', 'team.name', 'team.short_name as shortName', 'team.city']);
        $fixtures = DB::table('tournament_fixtures as fixture')
            ->join('tournament_teams as home_registration', 'home_registration.id', '=', 'fixture.home_tournament_team_id')
            ->join('teams as home', 'home.id', '=', 'home_registration.team_id')
            ->join('tournament_teams as away_registration', 'away_registration.id', '=', 'fixture.away_tournament_team_id')
            ->join('teams as away', 'away.id', '=', 'away_registration.team_id')
            ->where('fixture.tournament_id', $tournament->id)->orderBy('fixture.scheduled_at')
            ->get(['fixture.id', 'fixture.match_id as matchId', 'fixture.home_tournament_team_id as homeTournamentTeamId', 'fixture.away_tournament_team_id as awayTournamentTeamId', 'fixture.stage', 'fixture.group_name as groupName', 'fixture.round_number as roundNumber', 'fixture.scheduled_at as scheduledAtUtc', 'fixture.venue', 'fixture.status', 'home.name as homeName', 'away.name as awayName']);
        $standings = DB::table('tournament_standings as standing')->join('tournament_teams as registration', 'registration.id', '=', 'standing.tournament_team_id')->join('teams as team', 'team.id', '=', 'registration.team_id')->where('standing.tournament_id', $tournament->id)->orderByDesc('standing.points')->orderByDesc('standing.net_run_rate')->get(['team.name', 'standing.played', 'standing.won', 'standing.lost', 'standing.tied', 'standing.no_result as noResult', 'standing.points', 'standing.net_run_rate as netRunRate']);
        $projections = DB::table('tournament_projection_documents')->where('tournament_id', $tournament->id)->pluck('payload', 'kind')->map(fn ($value) => json_decode($value, true));

        return response()->json(['data' => ['id' => $tournament->id, 'name' => $tournament->name, 'status' => $tournament->status, 'city' => $tournament->city, 'season' => $tournament->season, 'isManager' => $this->isManager($request, $tournament), 'teams' => $teams, 'fixtures' => $fixtures, 'standings' => $standings, 'statistics' => $projections->get('statistics', []), 'news' => $projections->get('news', []), 'honors' => $tournament->status === 'completed' ? $projections->get('honors', []) : []]]);
    }

    public function registerTeam(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeManager($request, $tournament);
        $data = $request->validate(['id' => ['required', 'ulid'], 'snapshotId' => ['required', 'ulid'], 'teamId' => ['required', 'ulid', 'exists:teams,id'], 'playerIds' => ['required', 'array', 'min:2'], 'playerIds.*' => ['ulid', 'distinct']]);
        $eligible = DB::table('team_memberships')->where('team_id', $data['teamId'])->where('status', 'active')->whereNull('left_at')->whereIn('player_id', $data['playerIds'])->count();
        abort_unless($eligible === count($data['playerIds']), 422, 'Every squad player must be an active team member.');
        DB::transaction(function () use ($data, $tournament): void {
            DB::table('tournament_teams')->insertOrIgnore(['id' => $data['id'], 'tournament_id' => $tournament->id, 'team_id' => $data['teamId'], 'status' => 'accepted', 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tournament_squad_snapshots')->insertOrIgnore(['id' => $data['snapshotId'], 'tournament_team_id' => $data['id'], 'version' => 1, 'roster' => json_encode($data['playerIds']), 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tournament_standings')->insertOrIgnore(['tournament_id' => $tournament->id, 'tournament_team_id' => $data['id'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => ['id' => $data['id']]], 201);
    }

    public function storeFixture(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeManager($request, $tournament);
        $data = $request->validate(['id' => ['required', 'ulid'], 'homeTournamentTeamId' => ['required', 'ulid', 'different:awayTournamentTeamId'], 'awayTournamentTeamId' => ['required', 'ulid'], 'stage' => ['required', 'string', 'max:40'], 'groupName' => ['nullable', 'string', 'max:40'], 'roundNumber' => ['nullable', 'integer', 'min:1'], 'scheduledAtUtc' => ['required', 'date'], 'venue' => ['required', 'string', 'max:150']]);
        $registered = DB::table('tournament_teams')->where('tournament_id', $tournament->id)->where('status', 'accepted')->whereIn('id', [$data['homeTournamentTeamId'], $data['awayTournamentTeamId']])->count();
        abort_unless($registered === 2, 422, 'Fixtures may only use accepted tournament teams.');
        DB::table('tournament_fixtures')->insert(['id' => $data['id'], 'tournament_id' => $tournament->id, 'home_tournament_team_id' => $data['homeTournamentTeamId'], 'away_tournament_team_id' => $data['awayTournamentTeamId'], 'stage' => $data['stage'], 'group_name' => $data['groupName'] ?? null, 'round_number' => $data['roundNumber'] ?? null, 'scheduled_at' => $data['scheduledAtUtc'], 'venue' => $data['venue'], 'status' => 'scheduled', 'rule_profile_version' => $tournament->rule_profile_version, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $data['id'], 'ruleProfileVersion' => $tournament->rule_profile_version]], 201);
    }

    public function confirmResult(Request $request, Tournament $tournament, string $fixture): JsonResponse
    {
        $this->authorizeManager($request, $tournament);
        $data = $request->validate(['id' => ['required', 'ulid'], 'outcome' => ['required', Rule::in(['home_win', 'away_win', 'tie', 'no_result'])], 'homeRuns' => ['required', 'integer', 'min:0'], 'homeLegalBalls' => ['required', 'integer', 'min:1'], 'awayRuns' => ['required', 'integer', 'min:0'], 'awayLegalBalls' => ['required', 'integer', 'min:1']]);
        $row = DB::table('tournament_fixtures')->where('id', $fixture)->where('tournament_id', $tournament->id)->first();
        abort_if($row === null, 404);
        DB::transaction(function () use ($data, $row, $request, $tournament): void {
            $winner = $data['outcome'] === 'home_win' ? $row->home_tournament_team_id : ($data['outcome'] === 'away_win' ? $row->away_tournament_team_id : null);
            $values = ['winner_tournament_team_id' => $winner, 'outcome' => $data['outcome'], 'home_runs' => $data['homeRuns'], 'home_legal_balls' => $data['homeLegalBalls'], 'away_runs' => $data['awayRuns'], 'away_legal_balls' => $data['awayLegalBalls'], 'confirmed_by_user_id' => $request->user()->id, 'confirmed_at' => now(), 'updated_at' => now()];
            $existing = DB::table('tournament_results')->where('fixture_id', $row->id)->first();
            if ($existing === null) {
                DB::table('tournament_results')->insert(['id' => $data['id'], 'fixture_id' => $row->id, ...$values, 'version' => 1, 'created_at' => now()]);
            } else {
                DB::table('tournament_results')->where('fixture_id', $row->id)->update([...$values, 'version' => $existing->version + 1]);
            }
            DB::table('tournament_fixtures')->where('id', $row->id)->update(['status' => 'completed', 'updated_at' => now()]);
            $this->rebuildStandings($tournament);
        }, 3);

        return response()->json(['data' => ['fixtureId' => $fixture, 'status' => 'confirmed']]);
    }

    public function startFixture(Request $request, Tournament $tournament, string $fixture, CreateMatchAction $create): JsonResponse
    {
        $this->authorizeManager($request, $tournament);
        $row = DB::table('tournament_fixtures')->where('id', $fixture)->where('tournament_id', $tournament->id)->lockForUpdate()->first();
        abort_if($row === null, 404);
        abort_if($row->status !== 'scheduled', 409, 'Fixture is not available to start.');
        $teams = DB::table('tournament_teams')->whereIn('id', [$row->home_tournament_team_id, $row->away_tournament_team_id])->pluck('team_id', 'id');
        $match = $create->handle($request->user(), ['id' => (string) Str::ulid(), 'matchType' => 'tournament', 'homeTeamId' => $teams[$row->home_tournament_team_id], 'awayTeamId' => $teams[$row->away_tournament_team_id], 'scheduledAtUtc' => $row->scheduled_at, 'venue' => $row->venue, 'tournamentAuthorized' => true, 'rules' => ['version' => $tournament->rule_profile_version, 'oversPerInnings' => $tournament->overs_per_innings, 'ballsPerOver' => $tournament->balls_per_over, 'playersPerSide' => $tournament->players_per_side, 'wicketsPerInnings' => $tournament->wickets_per_innings, 'ballType' => $tournament->ball_type]]);
        DB::table('tournament_fixtures')->where('id', $fixture)->update(['match_id' => $match->id, 'status' => 'ready', 'updated_at' => now()]);

        return response()->json(['data' => ['matchId' => $match->id, 'status' => 'ready']]);
    }

    public function statistics(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeViewer($request, $tournament);
        $offset = max(0, $request->integer('cursor'));
        $limit = min(50, max(1, $request->integer('limit', 20)));
        $payload = DB::table('tournament_projection_documents')->where(['tournament_id' => $tournament->id, 'kind' => 'statistics'])->value('payload');
        $items = $payload === null ? [] : json_decode($payload, true);
        $page = array_slice($items, $offset, $limit);

        return response()->json(['data' => $page, 'meta' => ['nextCursor' => $offset + count($page) < count($items) ? $offset + count($page) : null]]);
    }

    private function rebuildStandings(Tournament $tournament): void
    {
        $registrations = DB::table('tournament_teams')->where('tournament_id', $tournament->id)->pluck('id');
        $rates = [];
        foreach ($registrations as $id) {
            DB::table('tournament_standings')->where(['tournament_id' => $tournament->id, 'tournament_team_id' => $id])->update(['played' => 0, 'won' => 0, 'lost' => 0, 'tied' => 0, 'no_result' => 0, 'points' => 0, 'net_run_rate' => 0, 'source_version' => DB::raw('source_version + 1'), 'updated_at' => now()]);
            $rates[$id] = ['forRuns' => 0, 'forBalls' => 0, 'againstRuns' => 0, 'againstBalls' => 0];
        }
        $results = DB::table('tournament_results as result')->join('tournament_fixtures as fixture', 'fixture.id', '=', 'result.fixture_id')->where('fixture.tournament_id', $tournament->id)->get();
        foreach ($results as $result) {
            $home = $result->outcome === 'home_win' ? ['won' => 1, 'points' => $tournament->points_for_win] : ($result->outcome === 'away_win' ? ['lost' => 1, 'points' => 0] : ($result->outcome === 'tie' ? ['tied' => 1, 'points' => $tournament->points_for_tie] : ['no_result' => 1, 'points' => $tournament->points_for_no_result]));
            $away = $result->outcome === 'away_win' ? ['won' => 1, 'points' => $tournament->points_for_win] : ($result->outcome === 'home_win' ? ['lost' => 1, 'points' => 0] : $home);
            foreach ([[$result->home_tournament_team_id, $home], [$result->away_tournament_team_id, $away]] as [$team, $values]) {
                DB::table('tournament_standings')->where(['tournament_id' => $tournament->id, 'tournament_team_id' => $team])->incrementEach(['played' => 1, array_key_first($values) => 1, 'points' => $values['points']]);
            }
            $rates[$result->home_tournament_team_id]['forRuns'] += $result->home_runs;
            $rates[$result->home_tournament_team_id]['forBalls'] += $result->home_legal_balls;
            $rates[$result->home_tournament_team_id]['againstRuns'] += $result->away_runs;
            $rates[$result->home_tournament_team_id]['againstBalls'] += $result->away_legal_balls;
            $rates[$result->away_tournament_team_id]['forRuns'] += $result->away_runs;
            $rates[$result->away_tournament_team_id]['forBalls'] += $result->away_legal_balls;
            $rates[$result->away_tournament_team_id]['againstRuns'] += $result->home_runs;
            $rates[$result->away_tournament_team_id]['againstBalls'] += $result->home_legal_balls;
        }
        foreach ($rates as $teamId => $rate) {
            $forRate = $rate['forBalls'] === 0 ? 0 : ($rate['forRuns'] * 6) / $rate['forBalls'];
            $againstRate = $rate['againstBalls'] === 0 ? 0 : ($rate['againstRuns'] * 6) / $rate['againstBalls'];
            DB::table('tournament_standings')->where(['tournament_id' => $tournament->id, 'tournament_team_id' => $teamId])->update(['net_run_rate' => round($forRate - $againstRate, 4)]);
        }
    }

    private function isManager(Request $request, Tournament $tournament): bool
    {
        return $tournament->owner_user_id === $request->user()->id || DB::table('tournament_collaborators')->where(['tournament_id' => $tournament->id, 'user_id' => $request->user()->id])->whereIn('role', ['manager', 'scorer'])->exists();
    }

    private function authorizeManager(Request $request, Tournament $tournament): void
    {
        abort_unless($this->isManager($request, $tournament), 403);
    }

    private function authorizeViewer(Request $request, Tournament $tournament): void
    {
        abort_unless($this->isManager($request, $tournament) || DB::table('tournament_collaborators')->where(['tournament_id' => $tournament->id, 'user_id' => $request->user()->id])->exists(), 403);
    }
}
