<?php

namespace Tests\Feature\Api\V1\Players;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorePlayerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/players', [])->assertUnauthorized();
    }

    public function test_valid_payload_creates_player_and_returns_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $id = (string) Str::ulid();
        $idempotencyKey = (string) Str::ulid();

        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)->postJson('/api/v1/players', [
            'id' => Str::lower($id),
            'name' => '  Ahmed   Ali  ',
            'city' => 'Lahore',
            'playingRole' => 'all_rounder',
            'battingStyle' => 'right_hand',
            'bowlingStyle' => 'right_arm_medium',
            'bio' => 'Local club player.',
            'baseVersion' => 0,
            'createdByUserId' => (string) Str::ulid(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('name', 'Ahmed Ali')
            ->assertJsonPath('claimStatus', 'unclaimed')
            ->assertJsonPath('version', 1);
        $this->assertMatchesRegularExpression('/^STP-P-[0-9A-HJKMNP-TV-Z]{8}$/', $response->json('playerCode'));
        $this->assertDatabaseHas('players', [
            'id' => $id,
            'created_by_user_id' => $user->id,
            'normalized_name' => 'ahmed ali',
        ]);
    }

    public function test_returns_422_when_idempotency_header_and_required_fields_are_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/players', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors(['id', 'name', 'idempotencyKey'], 'error.fieldErrors');
    }

    public function test_identical_canonical_request_is_an_idempotent_replay(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => (string) Str::ulid()];
        $payload = $this->validPayload();

        $first = $this->withHeaders($headers)->postJson('/api/v1/players', $payload)->assertCreated();
        $second = $this->withHeaders($headers)->postJson('/api/v1/players', $payload)->assertCreated();

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('players', 1);
    }

    public function test_returns_409_when_canonical_id_is_reused_with_different_data(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => (string) Str::ulid()];
        $payload = $this->validPayload();
        $this->withHeaders($headers)->postJson('/api/v1/players', $payload)->assertCreated();

        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/players', [...$payload, 'name' => 'Different Player'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'canonical_identity_conflict')
            ->assertJsonPath('error.conflict.entityId', $payload['id']);

        $this->assertDatabaseCount('players', 1);
    }

    public function test_returns_409_when_idempotency_key_is_reused_for_different_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $key = (string) Str::ulid();
        $payload = $this->validPayload();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/players', $payload)->assertCreated();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/players', [...$payload, 'id' => (string) Str::ulid()])
            ->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => 'Ahmed Ali',
            'city' => 'Lahore',
            'playingRole' => 'all_rounder',
            'battingStyle' => 'right_hand',
            'bowlingStyle' => 'right_arm_medium',
            'bio' => null,
            'baseVersion' => 0,
        ];
    }
}
