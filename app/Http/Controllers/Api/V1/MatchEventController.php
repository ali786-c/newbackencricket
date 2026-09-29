<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matches\Actions\CorrectMatchEventAction;
use App\Domains\Matches\Actions\IngestMatchEventBatchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matches\StoreCorrectionRequest;
use App\Http\Requests\Api\V1\Matches\StoreEventBatchRequest;
use App\Models\CricketMatch;
use App\Models\MatchEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchEventController extends Controller
{
    public function batch(StoreEventBatchRequest $request, CricketMatch $match, IngestMatchEventBatchAction $ingest): JsonResponse
    {
        $result = $ingest->handle($match, $request->user(), $request->string('scoringSessionId'), $request->string('deviceId'), $request->integer('baseServerVersion'), $request->validated('events'));

        return response()->json(['data' => $result, 'meta' => ['apiVersion' => 'v1']]);
    }

    public function index(Request $request, CricketMatch $match): JsonResponse
    {
        $after = max(0, $request->integer('afterSequence'));
        $events = MatchEvent::query()->where('match_id', $match->id)->where('sequence', '>', $after)->orderBy('sequence')->limit(200)->get()->map(fn (MatchEvent $event): array => [
            'eventId' => $event->id,
            'sequence' => $event->sequence,
            'eventType' => $event->event_type,
            'eventSchemaVersion' => $event->event_schema_version,
            'ruleProfileVersion' => $event->rule_profile_version,
            'deviceId' => $event->device_id,
            'scoringSessionId' => $event->scoring_session_id,
            'baseServerVersion' => $event->base_server_version,
            'inningsId' => $event->innings_id,
            'occurredAtUtc' => $event->occurred_at_utc->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'payload' => $event->payload_json,
            'supersedesEventId' => $event->supersedes_event_id,
        ])->all();

        return response()->json(['data' => $events, 'meta' => ['apiVersion' => 'v1', 'afterSequence' => $after, 'serverVersion' => $match->server_version]]);
    }

    public function correct(StoreCorrectionRequest $request, CricketMatch $match, CorrectMatchEventAction $correct): JsonResponse
    {
        $event = $correct->handle($match, $request->user(), $request->validated());

        return response()->json(['data' => ['eventId' => $event->id, 'sequence' => $event->sequence, 'status' => 'accepted', 'serverVersion' => $match->refresh()->server_version], 'meta' => ['apiVersion' => 'v1']], 201);
    }
}
