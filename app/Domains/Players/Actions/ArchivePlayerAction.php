<?php

namespace App\Domains\Players\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class ArchivePlayerAction
{
    public function handle(Player $player, int $baseVersion): void
    {
        DB::transaction(function () use ($player, $baseVersion): void {
            $locked = Player::query()->whereKey($player->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            if ($locked->version !== $baseVersion) {
                throw new VersionConflictException('player', $locked->id, $locked->version);
            }

            $locked->archived_at = now('UTC');
            $locked->version++;
            $locked->save();
        });
    }
}
