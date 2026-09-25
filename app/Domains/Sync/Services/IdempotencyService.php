<?php

namespace App\Domains\Sync\Services;

use App\Domains\Sync\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyRecord;
use App\Models\User;

class IdempotencyService
{
    /** @param array<string, mixed> $payload */
    public function findReplay(User $user, string $operation, string $key, array $payload): ?IdempotencyRecord
    {
        $record = IdempotencyRecord::query()
            ->whereBelongsTo($user)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();

        if ($record !== null && ! hash_equals($record->request_hash, $this->hash($payload))) {
            throw new IdempotencyConflictException($operation, $key);
        }

        return $record;
    }

    /** @param array<string, mixed> $payload */
    public function record(
        User $user,
        string $operation,
        string $key,
        array $payload,
        string $resourceType,
        string $resourceId,
        int $responseStatus,
    ): IdempotencyRecord {
        return IdempotencyRecord::query()->create([
            'user_id' => $user->id,
            'operation' => $operation,
            'idempotency_key' => $key,
            'request_hash' => $this->hash($payload),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'response_status' => $responseStatus,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        unset($payload['idempotencyKey']);
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
