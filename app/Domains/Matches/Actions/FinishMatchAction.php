<?php

namespace App\Domains\Matches\Actions;

use App\Domains\Matches\Exceptions\MatchIngestionConflict;
use App\Models\CricketMatch;
use App\Models\MatchScoringSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FinishMatchAction
{
    public function __construct(private RebuildMatchProjectionAction $rebuild) {}

    /** @param array<string, mixed> $data */
    public function handle(CricketMatch $match, User $user, array $data): CricketMatch
    {
        return DB::transaction(function () use ($match, $user, $data): CricketMatch {
            $locked = CricketMatch::query()->lockForUpdate()->findOrFail($match->id);
            abort_unless($locked->created_by_user_id === $user->id, 403);
            if ($locked->finalized_at !== null) {
                throw new MatchIngestionConflict('match_finalized');
            }
            if ($locked->server_version !== $data['baseServerVersion']) {
                throw new MatchIngestionConflict('stale_base_version', ['serverVersion' => $locked->server_version]);
            }
            $session = MatchScoringSession::query()->whereKey($data['scoringSessionId'])->where('match_id', $locked->id)->where('user_id', $user->id)->where('device_id', $data['deviceId'])->where('state', 'active')->where('expires_at_utc', '>', now())->first();
            if ($session === null) {
                throw new MatchIngestionConflict('permission_revoked');
            }
            $locked->update(['status' => $data['resultType'], 'result_json' => ['type' => $data['resultType'], 'winnerTeamId' => $data['winnerTeamId'] ?? null, 'summary' => $data['summary']], 'finalized_at' => now(), 'server_version' => $locked->server_version + 1]);
            $session->update(['state' => 'released', 'released_at_utc' => now()]);
            $this->rebuild->handle($locked->refresh());

            return $locked->refresh();
        });
    }
}
