<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
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
            'teamCode' => $this->team_code,
            'ownerUserId' => $this->owner_user_id,
            'name' => $this->name,
            'shortName' => $this->short_name,
            'city' => $this->city,
            'description' => $this->description,
            'version' => $this->version,
            'createdAtUtc' => $this->created_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'updatedAtUtc' => $this->updated_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
