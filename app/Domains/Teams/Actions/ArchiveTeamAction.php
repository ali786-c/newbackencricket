<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Identity\Exceptions\VersionConflictException;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class ArchiveTeamAction
{
    public function handle(Team $team, int $baseVersion): void
    {
        DB::transaction(function () use ($team, $baseVersion): void {
            $locked = Team::query()->whereKey($team->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            if ($locked->version !== $baseVersion) {
                throw new VersionConflictException('team', $locked->id, $locked->version);
            }

            $locked->archived_at = now('UTC');
            $locked->version++;
            $locked->save();
        });
    }
}
