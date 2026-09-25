<?php

namespace Tests\Feature\Api\V1\Teams;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreTeamTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/teams', [])->assertUnauthorized();
    }

    public function test_valid_payload_creates_owned_team_and_returns_201(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = (string) Str::ulid();

        $response = $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson('/api/v1/teams', [
            'id' => Str::lower($id),
            'name' => '  Ali   Panthers ',
            'shortName' => 'ap',
            'city' => ' Lahore ',
            'description' => 'Community cricket team.',
            'baseVersion' => 0,
            'ownerUserId' => (string) Str::ulid(),
            'version' => 99,
        ]);

        $response->assertCreated()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('ownerUserId', $owner->id)
            ->assertJsonPath('name', 'Ali Panthers')
            ->assertJsonPath('shortName', 'AP')
            ->assertJsonPath('city', 'Lahore')
            ->assertJsonPath('version', 1);
        $this->assertMatchesRegularExpression('/^STP-T-[0-9A-HJKMNP-TV-Z]{8}$/', $response->json('teamCode'));
        $this->assertDatabaseHas('teams', [
            'id' => $id,
            'owner_user_id' => $owner->id,
            'normalized_name' => 'ali panthers',
            'version' => 1,
        ]);
    }

    public function test_returns_422_for_invalid_short_name_and_missing_idempotency_header(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/teams', [...$this->validPayload(), 'shortName' => 'A-TEAM'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors(['shortName', 'idempotencyKey'], 'error.fieldErrors');
    }

    public function test_identical_canonical_request_is_an_idempotent_replay(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => (string) Str::ulid()];
        $payload = $this->validPayload();

        $first = $this->withHeaders($headers)->postJson('/api/v1/teams', $payload)->assertCreated();
        $second = $this->withHeaders($headers)->postJson('/api/v1/teams', $payload)->assertCreated();

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    public function test_returns_409_when_another_owner_reuses_the_canonical_id(): void
    {
        $firstOwner = User::factory()->create();
        $payload = $this->validPayload();
        Sanctum::actingAs($firstOwner);
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/teams', $payload)
            ->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/teams', $payload)
            ->assertConflict()
            ->assertJsonPath('error.code', 'canonical_identity_conflict');

        $this->assertDatabaseCount('teams', 1);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => 'Ali Panthers',
            'shortName' => 'AP',
            'city' => 'Lahore',
            'description' => null,
            'baseVersion' => 0,
        ];
    }
}
