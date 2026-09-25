<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Domains\Teams\Exceptions\TeamOwnershipTransferConflictException;
use App\Models\Team;
use App\Models\TeamOwnershipTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecideTeamOwnershipTransferAction
{
    public function handle(Team $team, TeamOwnershipTransfer $transfer, User $actor, string $decision, int $baseVersion, ?string $reason): TeamOwnershipTransfer
    {
        return DB::transaction(function () use ($team, $transfer, $actor, $decision, $baseVersion, $reason): TeamOwnershipTransfer {
            $lockedTeam = Team::query()->whereKey($team->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $lockedTransfer = TeamOwnershipTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($lockedTeam->version !== $baseVersion) {
                throw new VersionConflictException('team', $lockedTeam->id, $lockedTeam->version);
            }
            if ($lockedTransfer->team_id !== $lockedTeam->id || $lockedTransfer->status !== 'pending') {
                throw new TeamOwnershipTransferConflictException('transfer_not_pending');
            }
            if ($lockedTransfer->to_user_id !== $actor->id || $lockedTransfer->from_user_id !== $lockedTeam->owner_user_id) {
                throw new TeamOwnershipTransferConflictException('ownership_changed');
            }

            if ($decision === 'accepted') {
                $lockedTeam->owner_user_id = $lockedTransfer->to_user_id;
                $lockedTeam->version++;
                $lockedTeam->save();
            }
            $lockedTransfer->status = $decision;
            $lockedTransfer->decided_by_user_id = $actor->id;
            $lockedTransfer->decision_reason = $reason;
            $lockedTransfer->decided_at = now('UTC');
            $lockedTransfer->save();

            return $lockedTransfer->load('team');
        });
    }
}
