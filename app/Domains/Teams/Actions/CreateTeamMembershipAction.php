<?php

namespace App\Domains\Teams\Actions;

use App\Domains\Teams\Exceptions\OverlappingTeamMembershipException;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTeamMembershipAction
{
    public function handle(
        Team $team,
        Player $player,
        CarbonInterface $joinedAt,
        ?CarbonInterface $leftAt = null,
        ?string $teamRole = null,
        ?string $membershipId = null,
    ): TeamMembership {
        if ($leftAt !== null && $leftAt->isBefore($joinedAt)) {
            throw new InvalidArgumentException('Membership end must not precede its start.');
        }

        return DB::transaction(function () use ($team, $player, $joinedAt, $leftAt, $teamRole, $membershipId): TeamMembership {
            Team::query()->whereKey($team->getKey())->lockForUpdate()->firstOrFail();

            $overlapExists = TeamMembership::query()
                ->whereBelongsTo($team)
                ->whereBelongsTo($player)
                ->where('joined_at', '<=', $leftAt ?? '9999-12-31 23:59:59')
                ->where(function ($query) use ($joinedAt): void {
                    $query->whereNull('left_at')->orWhere('left_at', '>=', $joinedAt);
                })
                ->lockForUpdate()
                ->exists();

            if ($overlapExists) {
                throw new OverlappingTeamMembershipException($team->id, $player->id);
            }

            $membership = new TeamMembership([
                'team_id' => $team->id,
                'player_id' => $player->id,
                'status' => $leftAt === null ? 'active' : 'past',
                'team_role' => $teamRole,
                'joined_at' => $joinedAt,
                'left_at' => $leftAt,
                'version' => 1,
            ]);
            if ($membershipId !== null) {
                $membership->id = $membershipId;
            }
            $membership->save();

            return $membership;
        });
    }
}
