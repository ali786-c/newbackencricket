<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Domains\Teams\Exceptions\TeamOwnershipTransferConflictException;
use App\Models\Team;
use App\Models\TeamOwnershipTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestTeamOwnershipTransferAction
{
    public function handle(Team $team, User $owner, User $recipient, int $baseVersion, ?string $message): TeamOwnershipTransfer
    {
        return DB::transaction(function () use ($team, $owner, $recipient, $baseVersion, $message): TeamOwnershipTransfer {
            $locked = Team::query()->whereKey($team->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            if ($locked->version !== $baseVersion) {
                throw new VersionConflictException('team', $locked->id, $locked->version);
            }
            if ($locked->owner_user_id !== $owner->id) {
                throw new TeamOwnershipTransferConflictException('owner_changed');
            }
            if ($owner->id === $recipient->id) {
                throw new TeamOwnershipTransferConflictException('recipient_is_current_owner');
            }
            if (TeamOwnershipTransfer::query()->where('team_id', $locked->id)->where('status', 'pending')->exists()) {
                throw new TeamOwnershipTransferConflictException('pending_transfer_exists');
            }

            return TeamOwnershipTransfer::query()->create([
                'team_id' => $locked->id,
                'from_user_id' => $owner->id,
                'to_user_id' => $recipient->id,
                'status' => 'pending',
                'message' => $message,
                'team_version_at_request' => $locked->version,
                'requested_at' => now('UTC'),
            ])->load('team');
        });
    }
}
