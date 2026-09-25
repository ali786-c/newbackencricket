<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teams\Actions\DecideTeamOwnershipTransferAction;
use App\Domains\Teams\Actions\RequestTeamOwnershipTransferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teams\DecideTeamOwnershipTransferRequest;
use App\Http\Requests\Api\V1\Teams\StoreTeamOwnershipTransferRequest;
use App\Http\Resources\Api\V1\TeamOwnershipTransferResource;
use App\Models\Team;
use App\Models\TeamOwnershipTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TeamOwnershipTransferController extends Controller
{
    public function store(StoreTeamOwnershipTransferRequest $request, Team $team, RequestTeamOwnershipTransferAction $requestTransfer): JsonResponse
    {
        $recipient = User::query()->findOrFail($request->validated('toUserId'));
        $transfer = $requestTransfer->handle($team, $request->user(), $recipient, $request->integer('baseVersion'), $request->validated('message'));

        return (new TeamOwnershipTransferResource($transfer))->response()->setStatusCode(201);
    }

    public function update(
        DecideTeamOwnershipTransferRequest $request,
        Team $team,
        TeamOwnershipTransfer $ownershipTransfer,
        DecideTeamOwnershipTransferAction $decideTransfer,
    ): TeamOwnershipTransferResource {
        return new TeamOwnershipTransferResource($decideTransfer->handle(
            $team,
            $ownershipTransfer,
            $request->user(),
            $request->string('decision')->toString(),
            $request->integer('baseVersion'),
            $request->validated('reason'),
        ));
    }
}
