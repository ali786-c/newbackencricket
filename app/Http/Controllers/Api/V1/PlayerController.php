<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Players\Actions\ArchivePlayerAction;
use App\Domains\Players\Actions\CreatePlayerAction;
use App\Domains\Players\Actions\UpdatePlayerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SearchIdentityRequest;
use App\Http\Requests\Api\V1\Players\ArchivePlayerRequest;
use App\Http\Requests\Api\V1\Players\StorePlayerRequest;
use App\Http\Requests\Api\V1\Players\UpdatePlayerRequest;
use App\Http\Resources\Api\V1\PlayerResource;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SearchIdentityRequest $request)
    {
        $query = Player::query()->whereNull('archived_at')->orderBy('normalized_name')->orderBy('id');
        if ($request->filled('code')) {
            $query->where('player_code', $request->validated('code'));
        }
        if ($request->filled('query')) {
            $query->where('normalized_name', 'like', '%'.$request->validated('query').'%');
        }

        return PlayerResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlayerRequest $request, CreatePlayerAction $create): JsonResponse
    {
        $player = $create->handle($request->user(), $request->validated());

        return (new PlayerResource($player))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Player $player): PlayerResource
    {
        abort_if($player->archived_at !== null, 404);

        return new PlayerResource($player);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlayerRequest $request, Player $player, UpdatePlayerAction $update): PlayerResource
    {
        return new PlayerResource($update->handle($player, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ArchivePlayerRequest $request, Player $player, ArchivePlayerAction $archive): Response
    {
        $archive->handle($player, $request->integer('baseVersion'));

        return response()->noContent();
    }
}
