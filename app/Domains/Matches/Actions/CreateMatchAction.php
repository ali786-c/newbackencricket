<?php

namespace App\Domains\Matches\Actions;

use App\Models\CricketMatch;
use App\Models\Innings;
use App\Models\MatchProjection;
use App\Models\MatchRuleProfile;
use App\Models\MatchTeamSnapshot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMatchAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $user, array $data): CricketMatch
    {
        return DB::transaction(function () use ($user, $data): CricketMatch {
            $home = Team::query()->findOrFail($data['homeTeamId']);
            $away = Team::query()->findOrFail($data['awayTeamId']);
            abort_unless(($data['tournamentAuthorized'] ?? false) || $home->owner_user_id === $user->id || $away->owner_user_id === $user->id, 403);
            $match = CricketMatch::query()->create([
                'id' => $data['id'] ?? (string) Str::ulid(), 'created_by_user_id' => $user->id,
                'match_type' => $data['matchType'], 'status' => 'ready', 'home_team_id' => $home->id,
                'away_team_id' => $away->id, 'scheduled_at_utc' => $data['scheduledAtUtc'], 'venue' => $data['venue'],
            ]);
            $homeSnapshot = MatchTeamSnapshot::query()->create(['match_id' => $match->id, 'source_team_id' => $home->id, 'side' => 'home', 'name' => $home->name, 'short_name' => $home->short_name, 'team_code' => $home->team_code]);
            $awaySnapshot = MatchTeamSnapshot::query()->create(['match_id' => $match->id, 'source_team_id' => $away->id, 'side' => 'away', 'name' => $away->name, 'short_name' => $away->short_name, 'team_code' => $away->team_code]);
            $rules = $data['rules'];
            MatchRuleProfile::query()->create(['match_id' => $match->id, 'version' => $rules['version'], 'overs_per_innings' => $rules['oversPerInnings'], 'balls_per_over' => $rules['ballsPerOver'], 'players_per_side' => $rules['playersPerSide'], 'wickets_per_innings' => $rules['wicketsPerInnings'], 'ball_type' => $rules['ballType']]);
            Innings::query()->create(['match_id' => $match->id, 'innings_number' => 1, 'status' => 'pending', 'batting_team_snapshot_id' => $homeSnapshot->id, 'bowling_team_snapshot_id' => $awaySnapshot->id]);
            MatchProjection::query()->create(['match_id' => $match->id, 'source_sequence' => 0, 'server_version' => 0, 'schema_version' => 1, 'payload_json' => ['totalRuns' => 0, 'wickets' => 0, 'legalBalls' => 0, 'status' => 'ready']]);

            return $match->refresh();
        });
    }
}
