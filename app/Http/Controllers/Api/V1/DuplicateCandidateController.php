<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Actions\FindDuplicateCandidatesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\FindDuplicateCandidatesRequest;
use Illuminate\Http\JsonResponse;

class DuplicateCandidateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FindDuplicateCandidatesRequest $request, string $entityType, FindDuplicateCandidatesAction $find): JsonResponse
    {
        $candidates = $find->handle(
            $entityType,
            $request->string('name')->toString(),
            $request->string('city')->toString(),
            $request->user(),
            $request->validated('excludeId'),
        );

        return response()->json([
            'status' => $candidates->isEmpty() ? 'no_candidates' : 'possible_duplicate',
            'candidates' => $candidates,
        ]);
    }
}
