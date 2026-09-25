<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Players\Actions\DecidePlayerClaimAction;
use App\Domains\Players\Actions\RequestPlayerClaimAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Players\DecidePlayerClaimRequest;
use App\Http\Requests\Api\V1\Players\StorePlayerClaimRequest;
use App\Http\Resources\Api\V1\PlayerClaimRequestResource;
use App\Models\Player;
use App\Models\PlayerClaimRequest;
use Illuminate\Http\JsonResponse;

class PlayerClaimRequestController extends Controller
{
    public function store(
        StorePlayerClaimRequest $request,
        Player $player,
        RequestPlayerClaimAction $requestClaim,
    ): JsonResponse {
        $claimRequest = $requestClaim->handle(
            $player,
            $request->user(),
            $request->integer('baseVersion'),
            $request->validated('message'),
        );

        return (new PlayerClaimRequestResource($claimRequest))->response()->setStatusCode(201);
    }

    public function update(
        DecidePlayerClaimRequest $request,
        Player $player,
        PlayerClaimRequest $claimRequest,
        DecidePlayerClaimAction $decideClaim,
    ): PlayerClaimRequestResource {
        return new PlayerClaimRequestResource($decideClaim->handle(
            $player,
            $claimRequest,
            $request->user(),
            $request->string('decision')->toString(),
            $request->integer('baseVersion'),
            $request->validated('reason'),
        ));
    }
}
