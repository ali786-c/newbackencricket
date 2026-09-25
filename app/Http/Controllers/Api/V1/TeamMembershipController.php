<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teams\Actions\CreateTeamMembershipAction;
use App\Domains\Teams\Actions\EndTeamMembershipAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teams\EndTeamMembershipRequest;
use App\Http\Requests\Api\V1\Teams\StoreTeamMembershipRequest;
use App\Http\Resources\Api\V1\TeamMembershipResource;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamMembership;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TeamMembershipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamMembershipRequest $request, Team $team, CreateTeamMembershipAction $create): JsonResponse
    {
        $data = $request->validated();
        $membership = $create->handle(
            $team,
            Player::query()->findOrFail($data['playerId']),
            CarbonImmutable::parse($data['joinedAtUtc']),
            teamRole: $data['teamRole'] ?? null,
            membershipId: $data['id'],
        );

        return (new TeamMembershipResource($membership))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        EndTeamMembershipRequest $request,
        Team $team,
        TeamMembership $membership,
        EndTeamMembershipAction $end,
    ): TeamMembershipResource {
        abort_unless($membership->team_id === $team->id, 404);

        return new TeamMembershipResource($end->handle(
            $membership,
            CarbonImmutable::parse($request->validated('leftAtUtc')),
        ));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
