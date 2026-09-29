<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Exceptions\CanonicalIdentityConflictException;
use App\Domains\Sync\Services\IdempotencyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tournaments\StoreTournamentRequest;
use App\Http\Resources\Api\V1\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::query()
            ->where(function ($query) use ($request): void {
                $query->where('owner_user_id', $request->user()->id)
                    ->orWhereExists(function ($collaborator) use ($request): void {
                        $collaborator->selectRaw('1')->from('tournament_collaborators')
                            ->whereColumn('tournament_collaborators.tournament_id', 'tournaments.id')
                            ->where('tournament_collaborators.user_id', $request->user()->id);
                    });
            })->orderByDesc('updated_at');
        if ($request->filled('status') && $request->string('status') !== 'all') {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('query')) {
            $query->where('normalized_name', 'like', '%'.Str::lower($request->string('query')->squish()).'%');
        }

        return TournamentResource::collection($query->cursorPaginate(30)->withQueryString());
    }

    public function store(StoreTournamentRequest $request, IdempotencyService $idempotency): JsonResponse
    {
        $attributes = $request->validated();
        $tournament = DB::transaction(function () use ($request, $attributes, $idempotency): Tournament {
            $owner = $request->user();
            $replay = $idempotency->findReplay($owner, 'tournaments.create', $attributes['idempotencyKey'], $attributes);
            if ($replay !== null) {
                return Tournament::query()->findOrFail($replay->resource_id);
            }
            $existing = Tournament::query()->whereKey($attributes['id'])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->owner_user_id === $owner->id && $existing->name === $attributes['name']) {
                    return $existing;
                }
                throw new CanonicalIdentityConflictException('tournament', $attributes['id']);
            }
            $rules = $attributes['ruleProfile'];
            $points = $attributes['pointsRules'];
            $tournament = new Tournament([
                'owner_user_id' => $owner->id, 'name' => $attributes['name'],
                'normalized_name' => Str::lower(Str::squish($attributes['name'])),
                'city' => $attributes['city'], 'season' => $attributes['season'], 'status' => 'registration',
                'starts_at' => $attributes['startsAtUtc'], 'ends_at' => $attributes['endsAtUtc'],
                'description' => $attributes['description'] ?? null, 'logo_path' => $attributes['logoPath'] ?? null,
                'rule_profile_version' => 1, 'overs_per_innings' => $rules['oversPerInnings'],
                'balls_per_over' => $rules['ballsPerOver'], 'players_per_side' => $rules['playersPerSide'],
                'wickets_per_innings' => $rules['wicketsPerInnings'], 'ball_type' => $rules['ballType'],
                'points_for_win' => $points['win'], 'points_for_tie' => $points['tie'],
                'points_for_no_result' => $points['noResult'], 'version' => 1,
            ]);
            $tournament->id = $attributes['id'];
            $tournament->save();
            $idempotency->record($owner, 'tournaments.create', $attributes['idempotencyKey'], $attributes, 'tournament', $tournament->id, 201);

            return $tournament;
        }, attempts: 3);

        return (new TournamentResource($tournament))->response()->setStatusCode(201);
    }

    public function show(Request $request, Tournament $tournament): TournamentResource
    {
        $canView = $tournament->owner_user_id === $request->user()->id
            || DB::table('tournament_collaborators')->where('tournament_id', $tournament->id)
                ->where('user_id', $request->user()->id)->exists();
        abort_unless($canView, 403);

        return new TournamentResource($tournament);
    }
}
