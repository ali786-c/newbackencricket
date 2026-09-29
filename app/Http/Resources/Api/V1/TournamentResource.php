<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ownerUserId' => $this->owner_user_id,
            'name' => $this->name,
            'city' => $this->city,
            'season' => $this->season,
            'status' => $this->status,
            'startsAtUtc' => $this->starts_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'endsAtUtc' => $this->ends_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'description' => $this->description,
            'logoPath' => $this->logo_path,
            'ruleProfile' => [
                'version' => $this->rule_profile_version,
                'oversPerInnings' => $this->overs_per_innings,
                'ballsPerOver' => $this->balls_per_over,
                'playersPerSide' => $this->players_per_side,
                'wicketsPerInnings' => $this->wickets_per_innings,
                'ballType' => $this->ball_type,
            ],
            'pointsRules' => ['win' => $this->points_for_win, 'tie' => $this->points_for_tie, 'noResult' => $this->points_for_no_result],
            'version' => $this->version,
            'createdAtUtc' => $this->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'updatedAtUtc' => $this->updated_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
