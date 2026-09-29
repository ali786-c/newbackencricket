<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'city' => $this->city,
            'playingRole' => $this->playing_role,
            'battingStyle' => $this->batting_style,
            'bowlingStyle' => $this->bowling_style,
            'bio' => $this->bio,
            'photoUrl' => $this->photo_url,
            'claimedPlayer' => $this->whenLoaded('claimedPlayer', fn () => $this->claimedPlayer === null ? null : [
                'id' => (string) $this->claimedPlayer->id,
                'playerCode' => $this->claimedPlayer->player_code,
                'name' => $this->claimedPlayer->name,
            ]),
            'createdAtUtc' => $this->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'updatedAtUtc' => $this->updated_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
