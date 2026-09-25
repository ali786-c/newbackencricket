<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\EntityMergeConflictException;
use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\EntityMergeRequest;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecideEntityMergeAction
{
    public function __construct(private CreateEntityAliasAction $createAlias) {}

    public function handle(EntityMergeRequest $mergeRequest, User $actor, string $decision): EntityMergeRequest
    {
        return DB::transaction(function () use ($mergeRequest, $actor, $decision): EntityMergeRequest {
            $request = EntityMergeRequest::query()->whereKey($mergeRequest->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw new EntityMergeConflictException('merge_not_pending');
            }
            if ($decision === 'cancel') {
                $request->update(['status' => 'cancelled', 'decided_by_user_id' => $actor->id, 'decided_at' => now('UTC')]);

                return $request;
            }
            $model = $request->entity_type === 'player' ? Player::class : Team::class;
            $entities = $model::query()->whereKey([$request->retired_id, $request->surviving_id])->whereNull('archived_at')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $retired = $entities->get($request->retired_id);
            $surviving = $entities->get($request->surviving_id);
            if (! $retired || ! $surviving) {
                throw new EntityMergeConflictException('entity_missing_or_archived');
            }
            if ($retired->version !== $request->retired_base_version) {
                throw new VersionConflictException($request->entity_type, $retired->id, $retired->version);
            }
            if ($surviving->version !== $request->surviving_base_version) {
                throw new VersionConflictException($request->entity_type, $surviving->id, $surviving->version);
            }

            $this->remapLiveRelations($request->entity_type, $retired, $surviving);
            $this->createAlias->handle($request->entity_type, $retired->id, $surviving->id, $actor, $request->reason);
            $retired->archived_at = now('UTC');
            $retired->version++;
            $retired->save();
            $surviving->version++;
            $surviving->save();
            $request->update(['status' => 'executed', 'decided_by_user_id' => $actor->id, 'decided_at' => now('UTC')]);

            return $request;
        });
    }

    private function remapLiveRelations(string $type, Player|Team $retired, Player|Team $surviving): void
    {
        $column = $type === 'player' ? 'player_id' : 'team_id';
        $other = $type === 'player' ? 'team_id' : 'player_id';
        $retiredKeys = TeamMembership::query()->where($column, $retired->id)->pluck($other);
        if (TeamMembership::query()->where($column, $surviving->id)->whereIn($other, $retiredKeys)->exists()) {
            throw new EntityMergeConflictException('membership_overlap_requires_manual_resolution');
        }
        TeamMembership::query()->where($column, $retired->id)->update([$column => $surviving->id]);
        if ($type === 'player' && $retired->claimed_user_id !== null) {
            if ($surviving->claimed_user_id !== null && $surviving->claimed_user_id !== $retired->claimed_user_id) {
                throw new EntityMergeConflictException('conflicting_claims');
            }
            $surviving->claimed_user_id = $retired->claimed_user_id;
            $surviving->claim_status = 'claimed';
            $retired->claimed_user_id = null;
            $retired->claim_status = 'unclaimed';
        }
    }
}
