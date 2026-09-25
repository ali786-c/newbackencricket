<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerClaimRequestResource extends JsonResource
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
            'playerId' => $this->player_id,
            'claimantUserId' => $this->claimant_user_id,
            'status' => $this->status,
            'playerVersionAtRequest' => $this->player_version_at_request,
            'currentPlayerVersion' => $this->whenLoaded('player', fn (): int => $this->player->version),
            'requestedAtUtc' => $this->requested_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'decidedAtUtc' => $this->decided_at?->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
