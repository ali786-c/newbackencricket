<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
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
            'playerCode' => $this->player_code,
            'name' => $this->name,
            'city' => $this->city,
            'claimStatus' => $this->claim_status,
            'playingRole' => $this->playing_role,
            'battingStyle' => $this->batting_style,
            'bowlingStyle' => $this->bowling_style,
            'bio' => $this->bio,
            'version' => $this->version,
            'createdAtUtc' => $this->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'updatedAtUtc' => $this->updated_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
