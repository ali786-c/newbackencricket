<?php

namespace Tests\Feature\Api\V1\Matches;

use App\Models\CricketMatch;
use App\Models\Innings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_match_acquire_lock_and_ingest_ordered_events(): void
    {
        [$user, $match, $session, $device, $innings] = $this->matchContext();
        $event = $this->event(1, $innings->id);

        $response = $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", [
            'scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$event],
        ]);

        $response->assertOk()->assertJsonPath('data.serverVersion', 1)->assertJsonPath('data.events.0.status', 'accepted');
        $this->assertDatabaseHas('match_events', ['id' => $event['eventId'], 'sequence' => 1]);
        $this->actingAs($user)->getJson("/api/v1/matches/{$match->id}/events?afterSequence=0")
            ->assertOk()
            ->assertJsonPath('data.0.eventId', $event['eventId'])
            ->assertJsonPath('data.0.eventSchemaVersion', 1)
            ->assertJsonPath('data.0.ruleProfileVersion', 1)
            ->assertJsonPath('data.0.deviceId', $device)
            ->assertJsonPath('data.0.scoringSessionId', $session)
            ->assertJsonPath('data.0.baseServerVersion', 0)
            ->assertJsonPath('meta.afterSequence', 0)
            ->assertJsonPath('meta.serverVersion', 1);
        $this->actingAs($user)->getJson("/api/v1/matches/{$match->id}/projection")->assertOk()
            ->assertJsonPath('data.projection.totalRuns', 4)->assertJsonPath('data.projection.legalBalls', 1);
    }

    public function test_identical_batch_replay_is_idempotent(): void
    {
        [$user, $match, $session, $device, $innings] = $this->matchContext();
        $payload = ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$this->event(1, $innings->id)]];
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", $payload)->assertOk();
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", $payload)->assertOk()->assertJsonPath('data.events.0.status', 'duplicate')->assertJsonPath('data.serverVersion', 1);
        $this->assertDatabaseCount('match_events', 1);
    }

    public function test_invalid_later_event_rolls_back_the_entire_batch(): void
    {
        [$user, $match, $session, $device, $innings] = $this->matchContext();

        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", [
            'scoringSessionId' => $session,
            'deviceId' => $device,
            'baseServerVersion' => 0,
            'events' => [$this->event(1, $innings->id), $this->event(3, $innings->id)],
        ])->assertConflict()->assertJsonPath('error.code', 'sequence_gap');

        $this->assertDatabaseCount('match_events', 0);
        $this->assertSame(0, $match->fresh()->server_version);
    }

    public function test_stale_version_sequence_gap_and_rule_mismatch_are_explicit_conflicts(): void
    {
        [$user, $match, $session, $device, $innings] = $this->matchContext();
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$this->event(1, $innings->id)]])->assertOk();

        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$this->event(2, $innings->id)]])->assertConflict()->assertJsonPath('error.code', 'stale_base_version');
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 1, 'events' => [$this->event(3, $innings->id)]])->assertConflict()->assertJsonPath('error.code', 'sequence_gap');
        $wrongRule = $this->event(2, $innings->id);
        $wrongRule['ruleProfileVersion'] = 2;
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 1, 'events' => [$wrongRule]])->assertConflict()->assertJsonPath('error.code', 'rule_profile_mismatch');
    }

    public function test_other_device_cannot_silently_take_scoring_lock(): void
    {
        [$user, $match] = $this->matchContext();
        $otherDevice = (string) Str::ulid();
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/scoring-sessions", ['deviceId' => $otherDevice, 'baseServerVersion' => 0])->assertConflict()->assertJsonPath('error.code', 'different_scoring_device');
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/scoring-sessions", ['deviceId' => $otherDevice, 'baseServerVersion' => 0, 'takeover' => true])->assertCreated()->assertJsonPath('data.deviceId', $otherDevice);
    }

    public function test_non_owner_is_forbidden_from_scoring(): void
    {
        [, $match, $session, $device, $innings] = $this->matchContext();
        $this->actingAs(User::factory()->create())->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$this->event(1, $innings->id)]])->assertForbidden();
    }

    public function test_correction_rebuilds_projection_and_finish_makes_history_immutable(): void
    {
        [$user, $match, $session, $device, $innings] = $this->matchContext();
        $event = $this->event(1, $innings->id);
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 0, 'events' => [$event]])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/corrections", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 1, 'eventId' => (string) Str::ulid(), 'targetEventId' => $event['eventId'], 'reason' => 'Scorer correction', 'payload' => ['batterRuns' => 6, 'extraRuns' => 0, 'isLegalDelivery' => true]])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/matches/{$match->id}/projection")->assertJsonPath('data.projection.totalRuns', 6);

        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/finish", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 2, 'resultType' => 'completed', 'winnerTeamId' => $match->home_team_id, 'summary' => 'Home won'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/events/batch", ['scoringSessionId' => $session, 'deviceId' => $device, 'baseServerVersion' => 3, 'events' => [$this->event(3, $innings->id)]])->assertConflict()->assertJsonPath('error.code', 'match_finalized');
    }

    /** @return array{User,CricketMatch,string,string,Innings} */
    private function matchContext(): array
    {
        $user = User::factory()->create();
        $home = Team::factory()->for($user, 'owner')->create();
        $away = Team::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/matches', ['matchType' => 'simple', 'homeTeamId' => $home->id, 'awayTeamId' => $away->id, 'scheduledAtUtc' => now()->addHour()->toISOString(), 'venue' => 'Club Ground', 'rules' => ['version' => 1, 'oversPerInnings' => 5, 'ballsPerOver' => 6, 'playersPerSide' => 6, 'wicketsPerInnings' => 5, 'ballType' => 'tennis']])->assertCreated();
        $match = CricketMatch::query()->findOrFail($response->json('data.id'));
        $device = (string) Str::ulid();
        $session = $this->actingAs($user)->postJson("/api/v1/matches/{$match->id}/scoring-sessions", ['deviceId' => $device, 'baseServerVersion' => 0])->assertCreated()->json('data.id');

        return [$user, $match, $session, $device, Innings::query()->where('match_id', $match->id)->firstOrFail()];
    }

    /** @return array<string, mixed> */
    private function event(int $sequence, string $inningsId): array
    {
        return ['eventId' => (string) Str::ulid(), 'inningsId' => $inningsId, 'sequence' => $sequence, 'eventType' => 'delivery', 'eventSchemaVersion' => 1, 'ruleProfileVersion' => 1, 'occurredAtUtc' => now()->toISOString(), 'payload' => ['batterRuns' => 4, 'extraRuns' => 0, 'isLegalDelivery' => true]];
    }
}
