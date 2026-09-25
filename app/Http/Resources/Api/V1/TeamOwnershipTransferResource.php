<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamOwnershipTransferResource extends JsonResource
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
            'fromUserId' => $this->from_user_id,
            'toUserId' => $this->to_user_id,
            'status' => $this->status,
            'teamVersionAtRequest' => $this->team_version_at_request,
            'currentTeamVersion' => $this->whenLoaded('team', fn (): int => $this->team->version),
            'requestedAtUtc' => $this->requested_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'decidedAtUtc' => $this->decided_at?->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
