<?php

namespace Tests\Feature\Domains\Sync\Services;

use App\Domains\Sync\Exceptions\IdempotencyConflictException;
use App\Domains\Sync\Services\IdempotencyService;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_record_for_identical_retry(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::ulid();
        $resourceId = (string) Str::ulid();
        $payload = ['id' => $resourceId, 'name' => 'Ali', 'idempotencyKey' => $key];
        $service = new IdempotencyService;

        $record = DB::transaction(function () use ($service, $user, $key, $payload, $resourceId) {
            $service->record($user, 'players.create', $key, $payload, 'player', $resourceId, 201);

            return $service->findReplay($user, 'players.create', $key, $payload);
        });

        $this->assertNotNull($record);
        $this->assertSame($resourceId, $record->resource_id);
        $this->assertSame(201, $record->response_status);
    }

    public function test_rejects_same_key_with_different_payload(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::ulid();
        $service = new IdempotencyService;
        $service->record(
            $user, 'teams.create', $key, ['id' => (string) Str::ulid(), 'name' => 'First'],
            'team', (string) Str::ulid(), 201,
        );

        $this->expectException(IdempotencyConflictException::class);

        DB::transaction(fn () => $service->findReplay(
            $user, 'teams.create', $key, ['id' => (string) Str::ulid(), 'name' => 'Second'],
        ));
    }
}
