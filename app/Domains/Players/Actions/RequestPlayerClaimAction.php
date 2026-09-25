<?php

namespace App\Domains\Players\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Domains\Players\Exceptions\PlayerClaimConflictException;
use App\Models\Player;
use App\Models\PlayerClaimRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestPlayerClaimAction
{
    public function handle(Player $player, User $claimant, int $baseVersion, ?string $message): PlayerClaimRequest
    {
        return DB::transaction(function () use ($player, $claimant, $baseVersion, $message): PlayerClaimRequest {
            $locked = Player::query()->whereKey($player->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            if ($locked->version !== $baseVersion) {
                throw new VersionConflictException('player', $locked->id, $locked->version);
            }
            if ($locked->claimed_user_id !== null || $locked->claim_status !== 'unclaimed') {
                throw new PlayerClaimConflictException('player_not_unclaimed');
            }
            if (Player::query()->where('claimed_user_id', $claimant->id)->exists()) {
                throw new PlayerClaimConflictException('claimant_already_has_player');
            }
            if (PlayerClaimRequest::query()->where('player_id', $locked->id)->where('status', 'pending')->exists()) {
                throw new PlayerClaimConflictException('pending_request_exists');
            }

            $claimRequest = PlayerClaimRequest::query()->create([
                'player_id' => $locked->id,
                'claimant_user_id' => $claimant->id,
                'status' => 'pending',
                'request_message' => $message,
                'player_version_at_request' => $locked->version,
                'requested_at' => now('UTC'),
            ]);
            $locked->claim_status = 'claim_pending';
            $locked->version++;
            $locked->save();

            return $claimRequest->load('player');
        });
    }
}
