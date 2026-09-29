<?php

namespace App\Domains\Sync\Actions;

use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IngestSyncOperationAction
{
    /**
     * @param  array{outbox_id: string, operation: string, entity_type: string, entity_id: string, payload: array<string, mixed>}  $data
     * @return array{operation: SyncOperation, duplicate: bool}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $hash = $this->hash($data);
            $existing = SyncOperation::query()
                ->whereBelongsTo($user)
                ->where('outbox_id', $data['outbox_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('Outbox ID was already used with different content.');
                }

                return ['operation' => $existing, 'duplicate' => true];
            }

            $operation = SyncOperation::query()->create([
                'user_id' => $user->id,
                ...$data,
                'request_hash' => $hash,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return ['operation' => $operation, 'duplicate' => false];
        });
    }

    /** @param array<string, mixed> $data */
    private function hash(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
