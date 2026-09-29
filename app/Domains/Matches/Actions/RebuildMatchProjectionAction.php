<?php

namespace App\Domains\Matches\Actions;

use App\Models\CricketMatch;
use App\Models\MatchEvent;
use App\Models\MatchProjection;

class RebuildMatchProjectionAction
{
    public function handle(CricketMatch $match): MatchProjection
    {
        $events = MatchEvent::query()->where('match_id', $match->id)->orderBy('sequence')->get();
        $superseded = $events->pluck('supersedes_event_id')->filter()->all();
        $runs = 0;
        $wickets = 0;
        $legalBalls = 0;
        foreach ($events as $event) {
            if (in_array($event->id, $superseded, true)) {
                continue;
            }
            $payload = $event->payload_json;
            $runs += (int) ($payload['totalRuns'] ?? ((int) ($payload['batterRuns'] ?? 0) + (int) ($payload['extraRuns'] ?? 0)));
            $wickets += ($event->event_type === 'wicket' || ($payload['isWicket'] ?? false) || isset($payload['dismissalType'])) ? 1 : 0;
            $legalBalls += (bool) ($payload['legalDelivery'] ?? $payload['isLegalDelivery'] ?? false) ? 1 : 0;
        }

        return MatchProjection::query()->updateOrCreate(['match_id' => $match->id], [
            'source_sequence' => $match->last_sequence,
            'server_version' => $match->server_version,
            'schema_version' => 1,
            'payload_json' => ['totalRuns' => $runs, 'wickets' => $wickets, 'legalBalls' => $legalBalls, 'status' => $match->status, 'result' => $match->result_json],
        ]);
    }
}
