<?php

namespace App\Domains\Matches\Actions;

use App\Domains\Matches\Exceptions\MatchIngestionConflict;
use App\Models\CricketMatch;
use App\Models\MatchEvent;
use App\Models\MatchRuleProfile;
use App\Models\MatchScoringSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IngestMatchEventBatchAction
{
    public function __construct(private RebuildMatchProjectionAction $rebuild) {}

    /** @param array<int, array<string, mixed>> $events
     * @return array{serverVersion:int,lastSequence:int,events:array<int,array<string,mixed>>}
     */
    public function handle(CricketMatch $match, User $user, string $sessionId, string $deviceId, int $baseVersion, array $events): array
    {
        return DB::transaction(function () use ($match, $user, $sessionId, $deviceId, $baseVersion, $events): array {
            $locked = CricketMatch::query()->lockForUpdate()->findOrFail($match->id);
            abort_unless($locked->created_by_user_id === $user->id, 403);
            if ($locked->finalized_at !== null) {
                throw new MatchIngestionConflict('match_finalized');
            }
            $session = MatchScoringSession::query()->lockForUpdate()->find($sessionId);
            if ($session === null || $session->match_id !== $locked->id || $session->user_id !== $user->id || $session->state !== 'active' || $session->expires_at_utc->isPast()) {
                throw new MatchIngestionConflict('permission_revoked');
            }
            if ($session->device_id !== $deviceId) {
                throw new MatchIngestionConflict('different_scoring_device');
            }

            $existingById = MatchEvent::query()->whereIn('id', collect($events)->pluck('eventId'))->get()->keyBy('id');
            $newEvents = [];
            $statuses = [];
            foreach ($events as $event) {
                $existing = $existingById->get($event['eventId']);
                if ($existing !== null) {
                    $same = $existing->match_id === $locked->id && $existing->sequence === $event['sequence'] && $existing->event_type === $event['eventType'] && $existing->payload_json === $event['payload'];
                    if (! $same) {
                        throw new MatchIngestionConflict('invalid_event_payload', ['eventId' => $event['eventId']]);
                    }
                    $statuses[] = ['eventId' => $event['eventId'], 'status' => 'duplicate', 'sequence' => $existing->sequence];

                    continue;
                }
                $newEvents[] = $event;
            }

            if ($newEvents !== [] && $locked->server_version !== $baseVersion) {
                throw new MatchIngestionConflict('stale_base_version', ['serverVersion' => $locked->server_version]);
            }
            $ruleProfileVersion = (int) MatchRuleProfile::query()->where('match_id', $locked->id)->max('version');
            usort($newEvents, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
            $expected = $locked->last_sequence + 1;
            foreach ($newEvents as $event) {
                $allowedTypes = ['delivery_recorded', 'delivery', 'bowler_changed', 'innings_started', 'innings_completed', 'match_completed', 'match_abandoned', 'match_forfeited', 'penalty_runs', 'retirement', 'batter_returned'];
                if (! in_array($event['eventType'], $allowedTypes, true) || $event['eventSchemaVersion'] !== 1) {
                    throw new MatchIngestionConflict('invalid_event_payload', ['eventId' => $event['eventId']]);
                }
                if (in_array($event['eventType'], ['delivery_recorded', 'delivery'], true) && ! array_key_exists('batterRuns', $event['payload'])) {
                    throw new MatchIngestionConflict('invalid_event_payload', ['eventId' => $event['eventId'], 'missing' => 'batterRuns']);
                }
                if ($event['sequence'] !== $expected) {
                    throw new MatchIngestionConflict('sequence_gap', ['expectedSequence' => $expected, 'receivedSequence' => $event['sequence']]);
                }
                if ($event['ruleProfileVersion'] !== $ruleProfileVersion) {
                    throw new MatchIngestionConflict('rule_profile_mismatch', ['expectedVersion' => $ruleProfileVersion]);
                }
                MatchEvent::query()->create(['id' => $event['eventId'], 'match_id' => $locked->id, 'innings_id' => $event['inningsId'] ?? null, 'scoring_session_id' => $session->id, 'device_id' => $deviceId, 'sequence' => $event['sequence'], 'event_type' => $event['eventType'], 'event_schema_version' => $event['eventSchemaVersion'], 'rule_profile_version' => $event['ruleProfileVersion'], 'base_server_version' => $baseVersion, 'occurred_at_utc' => $event['occurredAtUtc'], 'payload_json' => $event['payload']]);
                $statuses[] = ['eventId' => $event['eventId'], 'status' => 'accepted', 'sequence' => $event['sequence']];
                $expected++;
            }
            if ($newEvents !== []) {
                $locked->update(['status' => 'live', 'last_sequence' => $expected - 1, 'server_version' => $locked->server_version + 1]);
                $session->update(['base_server_version' => $locked->server_version, 'last_heartbeat_at_utc' => now(), 'expires_at_utc' => now()->addMinutes(5)]);
                $this->rebuild->handle($locked->refresh());
            }

            return ['serverVersion' => $locked->refresh()->server_version, 'lastSequence' => $locked->last_sequence, 'events' => $statuses];
        });
    }
}
