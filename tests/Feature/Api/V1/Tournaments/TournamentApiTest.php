<?php

namespace Tests\Feature\Api\V1\Tournaments;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_list_a_tournament_idempotently(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = (string) Str::ulid();
        $key = (string) Str::ulid();
        $payload = $this->payload($id);

        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/tournaments', $payload)
            ->assertCreated()->assertJsonPath('id', $id)->assertJsonPath('ownerUserId', $owner->id)
            ->assertJsonPath('ruleProfile.version', 1)->assertJsonPath('pointsRules.win', 2);
        $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/tournaments', $payload)->assertCreated();

        $this->assertSame($first->json(), $second->json());
        $this->getJson('/api/v1/tournaments')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('tournaments', 1);
    }

    public function test_invalid_dates_and_rules_are_rejected_without_writes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->payload((string) Str::ulid());
        $payload['endsAtUtc'] = '2026-10-01T00:00:00Z';
        $payload['ruleProfile']['wicketsPerInnings'] = 11;

        $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson('/api/v1/tournaments', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['endsAtUtc', 'ruleProfile.wicketsPerInnings'], 'error.fieldErrors');
        $this->assertDatabaseCount('tournaments', 0);
    }

    public function test_another_user_cannot_open_the_tournament(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = (string) Str::ulid();
        $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson('/api/v1/tournaments', $this->payload($id))->assertCreated();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/tournaments/{$id}")->assertForbidden();
    }

    public function test_collaborator_can_list_and_open_managed_tournament(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = (string) Str::ulid();
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/tournaments', $this->payload($id))->assertCreated();
        DB::table('tournament_collaborators')->insert([
            'id' => (string) Str::ulid(), 'tournament_id' => $id,
            'user_id' => $manager->id, 'role' => 'manager',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/tournaments')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/tournaments/{$id}")->assertOk()->assertJsonPath('id', $id);
    }

    /** @return array<string, mixed> */
    private function payload(string $id): array
    {
        return [
            'id' => $id, 'name' => 'Premier Cricket Cup', 'city' => 'Lahore', 'season' => '2026',
            'startsAtUtc' => '2026-10-01T00:00:00Z', 'endsAtUtc' => '2026-10-10T00:00:00Z',
            'description' => 'City tournament', 'baseVersion' => 0,
            'ruleProfile' => ['version' => 1, 'oversPerInnings' => 20, 'ballsPerOver' => 6, 'playersPerSide' => 11, 'wicketsPerInnings' => 10, 'ballType' => 'leather'],
            'pointsRules' => ['win' => 2, 'tie' => 1, 'noResult' => 1],
        ];
    }
}
