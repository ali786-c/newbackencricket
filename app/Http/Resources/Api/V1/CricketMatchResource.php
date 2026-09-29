<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CricketMatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'matchType' => $this->match_type, 'status' => $this->status,
            'homeTeamId' => (string) $this->home_team_id, 'awayTeamId' => (string) $this->away_team_id,
            'scheduledAtUtc' => $this->scheduled_at_utc->utc()->format('Y-m-d\TH:i:s.u\Z'), 'venue' => $this->venue,
            'serverVersion' => $this->server_version, 'lastSequence' => $this->last_sequence, 'result' => $this->result_json,
            'finalizedAtUtc' => $this->finalized_at?->utc()->format('Y-m-d\TH:i:s.u\Z')];
    }
}
