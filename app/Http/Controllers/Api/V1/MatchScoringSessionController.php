<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matches\Actions\AcquireScoringSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matches\StoreScoringSessionRequest;
use App\Models\CricketMatch;
use Illuminate\Http\JsonResponse;

class MatchScoringSessionController extends Controller
{
    public function store(StoreScoringSessionRequest $request, CricketMatch $match, AcquireScoringSessionAction $acquire): JsonResponse
    {
        $session = $acquire->handle($match, $request->user(), $request->string('deviceId'), $request->integer('baseServerVersion'), $request->boolean('takeover'));

        return response()->json(['data' => ['id' => $session->id, 'matchId' => $session->match_id, 'deviceId' => $session->device_id, 'state' => $session->state, 'baseServerVersion' => $session->base_server_version, 'expiresAtUtc' => $session->expires_at_utc->utc()->format('Y-m-d\TH:i:s.u\Z')], 'meta' => ['apiVersion' => 'v1']], 201);
    }
}
