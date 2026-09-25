<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EntityAlias;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IdentityResolutionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $publicCode): JsonResponse
    {
        $code = Str::upper(trim($publicCode));
        $entityType = str_starts_with($code, 'STP-P-') ? 'player' : (str_starts_with($code, 'STP-T-') ? 'team' : null);
        $entity = match ($entityType) {
            'player' => Player::query()->where('player_code', $code)->first(),
            'team' => Team::query()->where('team_code', $code)->first(),
            default => null,
        };
        if ($entity === null) {
            throw new NotFoundHttpException('Identity not found.');
        }

        $canonicalId = $entity->id;
        while ($alias = EntityAlias::query()->where('entity_type', $entityType)->where('retired_id', $canonicalId)->first()) {
            $canonicalId = $alias->surviving_id;
        }
        if ($entity->archived_at !== null && $canonicalId === $entity->id) {
            throw new NotFoundHttpException('Identity not found.');
        }

        return response()->json(['type' => $entityType, 'canonicalId' => $canonicalId, 'publicCode' => $code]);
    }
}
