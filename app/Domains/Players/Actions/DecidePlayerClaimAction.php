<?php

namespace App\Domains\Players\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Domains\Players\Exceptions\PlayerClaimConflictException;
use App\Models\Player;
use App\Models\PlayerClaimRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecidePlayerClaimAction
{
    public function handle(
        Player $player,
        PlayerClaimRequest $claimRequest,
        User $actor,
        string $decision,
        int $baseVersion,
        ?string $reason,
    ): PlayerClaimRequest {
        return DB::transaction(function () use ($player, $claimRequest, $actor, $decision, $baseVersion, $reason): PlayerClaimRequest {
            $lockedPlayer = Player::query()->whereKey($player->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $lockedRequest = PlayerClaimRequest::query()->whereKey($claimRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedPlayer->version !== $baseVersion) {
                throw new VersionConflictException('player', $lockedPlayer->id, $lockedPlayer->version);
            }
            if ($lockedRequest->player_id !== $lockedPlayer->id || $lockedRequest->status !== 'pending') {
                throw new PlayerClaimConflictException('request_not_pending');
            }
            if ($lockedPlayer->claim_status !== 'claim_pending' || $lockedPlayer->claimed_user_id !== null) {
                throw new PlayerClaimConflictException('player_not_claim_pending');
            }

            if ($decision === 'approved') {
                User::query()->whereKey($lockedRequest->claimant_user_id)->lockForUpdate()->firstOrFail();
                if (Player::query()->where('claimed_user_id', $lockedRequest->claimant_user_id)->exists()) {
                    throw new PlayerClaimConflictException('claimant_already_has_player');
                }
                $lockedPlayer->claimed_user_id = $lockedRequest->claimant_user_id;
                $lockedPlayer->claim_status = 'claimed';
            } else {
                $lockedPlayer->claim_status = 'unclaimed';
            }

            $lockedPlayer->version++;
            $lockedPlayer->save();
            $lockedRequest->status = $decision;
            $lockedRequest->decided_by_user_id = $actor->id;
            $lockedRequest->decision_reason = $reason;
            $lockedRequest->decided_at = now('UTC');
            $lockedRequest->save();

            return $lockedRequest->load('player');
        });
    }
}
