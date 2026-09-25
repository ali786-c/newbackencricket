<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityMergeRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'entityType' => $this->entity_type, 'retiredId' => $this->retired_id,
            'survivingId' => $this->surviving_id, 'status' => $this->status,
            'retiredBaseVersion' => $this->retired_base_version, 'survivingBaseVersion' => $this->surviving_base_version,
            'requestedAtUtc' => $this->requested_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'decidedAtUtc' => $this->decided_at?->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
