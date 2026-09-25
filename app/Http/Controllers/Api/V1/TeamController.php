<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teams\Actions\ArchiveTeamAction;
use App\Domains\Teams\Actions\CreateTeamAction;
use App\Domains\Teams\Actions\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SearchIdentityRequest;
use App\Http\Requests\Api\V1\Teams\ArchiveTeamRequest;
use App\Http\Requests\Api\V1\Teams\StoreTeamRequest;
use App\Http\Requests\Api\V1\Teams\UpdateTeamRequest;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SearchIdentityRequest $request)
    {
        $query = Team::query()->whereNull('archived_at')->orderBy('normalized_name')->orderBy('id');
        if ($request->filled('code')) {
            $query->where('team_code', $request->validated('code'));
        }
        if ($request->filled('query')) {
            $query->where('normalized_name', 'like', '%'.$request->validated('query').'%');
        }

        return TeamResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request, CreateTeamAction $create): JsonResponse
    {
        $team = $create->handle($request->user(), $request->validated());

        return (new TeamResource($team))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team): TeamResource
    {
        abort_if($team->archived_at !== null, 404);

        return new TeamResource($team);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team, UpdateTeamAction $update): TeamResource
    {
        return new TeamResource($update->handle($team, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ArchiveTeamRequest $request, Team $team, ArchiveTeamAction $archive): Response
    {
        $archive->handle($team, $request->integer('baseVersion'));

        return response()->noContent();
    }
}
