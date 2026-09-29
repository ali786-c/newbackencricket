<?php

namespace App\Domains\Matches\Actions;

use App\Domains\Matches\Exceptions\MatchIngestionConflict;
use App\Models\CricketMatch;
use App\Models\MatchScoringSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcquireScoringSessionAction
{
    public function handle(CricketMatch $match, User $user, string $deviceId, int $baseVersion, bool $takeover): MatchScoringSession
    {
        return DB::transaction(function () use ($match, $user, $deviceId, $baseVersion, $takeover): MatchScoringSession {
            $locked = CricketMatch::query()->lockForUpdate()->findOrFail($match->id);
            abort_unless($locked->created_by_user_id === $user->id, 403);
            if ($locked->finalized_at !== null) {
                throw new MatchIngestionConflict('match_finalized');
            }
            if ($locked->server_version !== $baseVersion) {
                throw new MatchIngestionConflict('stale_base_version', ['serverVersion' => $locked->server_version]);
            }
            $active = MatchScoringSession::query()->where('match_id', $locked->id)->where('state', 'active')->where('expires_at_utc', '>', now())->lockForUpdate()->first();
            if ($active !== null && $active->device_id !== $deviceId && ! $takeover) {
                throw new MatchIngestionConflict('different_scoring_device', ['activeDeviceId' => $active->device_id]);
            }
            if ($active !== null && $active->device_id !== $deviceId) {
                $active->update(['state' => 'revoked', 'released_at_utc' => now()]);
            }
            if ($active !== null && $active->device_id === $deviceId) {
                $active->update(['last_heartbeat_at_utc' => now(), 'expires_at_utc' => now()->addMinutes(5)]);

                return $active->refresh();
            }

            return MatchScoringSession::query()->create(['match_id' => $locked->id, 'user_id' => $user->id, 'device_id' => $deviceId, 'state' => 'active', 'base_server_version' => $locked->server_version, 'acquired_at_utc' => now(), 'last_heartbeat_at_utc' => now(), 'expires_at_utc' => now()->addMinutes(5)]);
        });
    }
}
