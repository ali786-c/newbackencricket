<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use App\Models\MatchProjection;
use Illuminate\Http\JsonResponse;

class MatchProjectionController extends Controller
{
    public function show(CricketMatch $match): JsonResponse
    {
        $projection = MatchProjection::query()->findOrFail($match->id);

        return response()->json(['data' => ['matchId' => $match->id, 'sourceSequence' => $projection->source_sequence, 'serverVersion' => $projection->server_version, 'schemaVersion' => $projection->schema_version, 'projection' => $projection->payload_json], 'meta' => ['apiVersion' => 'v1']]);
    }
}
