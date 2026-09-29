<?php

namespace Tests\Feature\Api\V1\Sync;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreSyncOperationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/sync/outbox', [])->assertUnauthorized();
    }

    public function test_accepts_an_outbox_operation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();

        $this->postJson('/api/v1/sync/outbox', $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.outbox_id', $payload['outbox_id'])
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseHas('sync_operations', [
            'outbox_id' => $payload['outbox_id'],
            'operation' => 'matches.events.append',
            'entity_id' => $payload['entity_id'],
            'status' => 'accepted',
        ]);
    }

    public function test_identical_retry_is_idempotent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();

        $this->postJson('/api/v1/sync/outbox', $payload)->assertStatus(202);
        $this->postJson('/api/v1/sync/outbox', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount('sync_operations', 1);
    }

    public function test_reused_outbox_id_with_different_content_conflicts(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();

        $this->postJson('/api/v1/sync/outbox', $payload)->assertStatus(202);
        $this->postJson('/api/v1/sync/outbox', [
            ...$payload,
            'payload' => ['sequence' => 2],
        ])->assertConflict();

        $this->assertDatabaseCount('sync_operations', 1);
    }

    public function test_rejects_invalid_contract(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/sync/outbox', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                ['outbox_id', 'operation', 'entity_type', 'entity_id', 'payload'],
                'error.fieldErrors',
            );
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'outbox_id' => (string) Str::ulid(),
            'operation' => 'matches.events.append',
            'entity_type' => 'match',
            'entity_id' => (string) Str::ulid(),
            'payload' => [
                'eventId' => (string) Str::ulid(),
                'sequence' => 1,
            ],
        ];
    }
}
