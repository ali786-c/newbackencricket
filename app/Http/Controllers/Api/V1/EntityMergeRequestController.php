<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Actions\DecideEntityMergeAction;
use App\Domains\Identity\Actions\RequestEntityMergeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\DecideEntityMergeRequest;
use App\Http\Requests\Api\V1\Identity\StoreEntityMergeRequest;
use App\Http\Resources\Api\V1\EntityMergeRequestResource;
use App\Models\EntityMergeRequest;
use Illuminate\Http\JsonResponse;

class EntityMergeRequestController extends Controller
{
    public function store(StoreEntityMergeRequest $request, RequestEntityMergeAction $requestMerge): JsonResponse
    {
        $merge = $requestMerge->handle($request->user(), $request->validated());

        return (new EntityMergeRequestResource($merge))->response()->setStatusCode(201);
    }

    public function update(DecideEntityMergeRequest $request, EntityMergeRequest $mergeRequest, DecideEntityMergeAction $decide): EntityMergeRequestResource
    {
        return new EntityMergeRequestResource($decide->handle($mergeRequest, $request->user(), $request->string('decision')->toString()));
    }
}
