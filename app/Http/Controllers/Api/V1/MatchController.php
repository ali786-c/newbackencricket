<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Matches\Actions\CreateMatchAction;
use App\Domains\Matches\Actions\FinishMatchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matches\FinishMatchRequest;
use App\Http\Requests\Api\V1\Matches\StoreMatchRequest;
use App\Http\Resources\Api\V1\CricketMatchResource;
use App\Models\CricketMatch;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function store(StoreMatchRequest $request, CreateMatchAction $create): JsonResponse
    {
        return response()->json(['data' => (new CricketMatchResource($create->handle($request->user(), $request->validated())))->resolve($request), 'meta' => ['apiVersion' => 'v1']], 201);
    }

    public function show(CricketMatch $match): JsonResponse
    {
        return response()->json(['data' => (new CricketMatchResource($match))->resolve(request()), 'meta' => ['apiVersion' => 'v1']]);
    }

    public function finish(FinishMatchRequest $request, CricketMatch $match, FinishMatchAction $finish): JsonResponse
    {
        return response()->json(['data' => (new CricketMatchResource($finish->handle($match, $request->user(), $request->validated())))->resolve($request), 'meta' => ['apiVersion' => 'v1']]);
    }
}
