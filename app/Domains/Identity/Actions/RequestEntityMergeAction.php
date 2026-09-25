<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\EntityMergeConflictException;
use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\EntityMergeRequest;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestEntityMergeAction
{
    public function handle(User $actor, array $data): EntityMergeRequest
    {
        return DB::transaction(function () use ($actor, $data): EntityMergeRequest {
            $model = $this->modelFor($data['entityType']);
            $entities = $model::query()->whereKey([$data['retiredId'], $data['survivingId']])->whereNull('archived_at')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $retired = $entities->get($data['retiredId']);
            $surviving = $entities->get($data['survivingId']);
            if (! $retired || ! $surviving || ! Gate::forUser($actor)->allows('update', $retired) || ! Gate::forUser($actor)->allows('update', $surviving)) {
                throw new EntityMergeConflictException('entities_missing_or_unauthorized');
            }
            if ($retired->version !== $data['retiredBaseVersion']) {
                throw new VersionConflictException($data['entityType'], $retired->id, $retired->version);
            }
            if ($surviving->version !== $data['survivingBaseVersion']) {
                throw new VersionConflictException($data['entityType'], $surviving->id, $surviving->version);
            }
            if (EntityMergeRequest::query()->where('entity_type', $data['entityType'])->where('status', 'pending')->where(fn ($query) => $query->where('retired_id', $retired->id)->orWhere('surviving_id', $retired->id)->orWhere('retired_id', $surviving->id)->orWhere('surviving_id', $surviving->id))->exists()) {
                throw new EntityMergeConflictException('pending_merge_exists');
            }

            return EntityMergeRequest::query()->create([
                'entity_type' => $data['entityType'], 'retired_id' => $retired->id, 'surviving_id' => $surviving->id,
                'requested_by_user_id' => $actor->id, 'status' => 'pending', 'reason' => $data['reason'],
                'retired_base_version' => $retired->version, 'surviving_base_version' => $surviving->version, 'requested_at' => now('UTC'),
            ]);
        });
    }

    /** @return class-string<Model> */
    private function modelFor(string $type): string
    {
        return $type === 'player' ? Player::class : Team::class;
    }
}
