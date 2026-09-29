<?php

namespace App\Domains\Matches\Actions;

use App\Domains\Matches\Exceptions\MatchIngestionConflict;
use App\Models\CricketMatch;
use App\Models\EventCorrection;
use App\Models\MatchEvent;
use App\Models\MatchScoringSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CorrectMatchEventAction
{
    public function __construct(private RebuildMatchProjectionAction $rebuild) {}

    /** @param array<string, mixed> $data */
    public function handle(CricketMatch $match, User $user, array $data): MatchEvent
    {
        return DB::transaction(function () use ($match, $user, $data): MatchEvent {
            $locked = CricketMatch::query()->lockForUpdate()->findOrFail($match->id);
            abort_unless($locked->created_by_user_id === $user->id, 403);
            if ($locked->server_version !== $data['baseServerVersion']) {
                throw new MatchIngestionConflict('stale_base_version', ['serverVersion' => $locked->server_version]);
            }
            $session = MatchScoringSession::query()->whereKey($data['scoringSessionId'])->where('match_id', $locked->id)->where('user_id', $user->id)->where('device_id', $data['deviceId'])->where('state', 'active')->where('expires_at_utc', '>', now())->first();
            if ($session === null) {
                throw new MatchIngestionConflict('permission_revoked');
            }
            $target = MatchEvent::query()->where('match_id', $locked->id)->findOrFail($data['targetEventId']);
            $correction = MatchEvent::query()->create(['id' => $data['eventId'], 'match_id' => $locked->id, 'innings_id' => $target->innings_id, 'scoring_session_id' => $session->id, 'device_id' => $data['deviceId'], 'sequence' => $locked->last_sequence + 1, 'event_type' => 'correction', 'event_schema_version' => 1, 'rule_profile_version' => $target->rule_profile_version, 'base_server_version' => $locked->server_version, 'occurred_at_utc' => now(), 'payload_json' => $data['payload'], 'supersedes_event_id' => $target->id]);
            EventCorrection::query()->create(['match_id' => $locked->id, 'target_event_id' => $target->id, 'correction_event_id' => $correction->id, 'created_by_user_id' => $user->id, 'reason' => $data['reason'] ?? null]);
            $locked->update(['last_sequence' => $correction->sequence, 'server_version' => $locked->server_version + 1]);
            $this->rebuild->handle($locked->refresh());

            return $correction;
        });
    }
}
