<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMembershipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teamId' => $this->team_id,
            'playerId' => $this->player_id,
            'status' => $this->status,
            'teamRole' => $this->team_role,
            'joinedAtUtc' => $this->joined_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'leftAtUtc' => $this->left_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
        ];
    }
}
