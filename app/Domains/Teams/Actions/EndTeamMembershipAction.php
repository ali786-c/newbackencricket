<?php

namespace App\Domains\Teams\Actions;

use App\Models\TeamMembership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EndTeamMembershipAction
{
    public function handle(TeamMembership $membership, CarbonInterface $leftAt): TeamMembership
    {
        return DB::transaction(function () use ($membership, $leftAt): TeamMembership {
            $locked = TeamMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if ($locked->left_at !== null) {
                return $locked;
            }
            if ($leftAt->isBefore($locked->joined_at)) {
                throw new \InvalidArgumentException('Membership end must not precede its start.');
            }
            $locked->left_at = $leftAt;
            $locked->status = 'past';
            $locked->version++;
            $locked->save();

            return $locked;
        });
    }
}
