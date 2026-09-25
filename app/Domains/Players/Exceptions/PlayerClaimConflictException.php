<?php

namespace App\Domains\Players\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PlayerClaimConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The player claim cannot be completed in its current state.');
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        return response()->json(['error' => [
            'code' => 'player_claim_conflict',
            'message' => $this->getMessage(),
            'requestId' => is_string($requestId) && Str::isUlid($requestId) ? Str::upper($requestId) : (string) Str::ulid(),
            'conflict' => ['reason' => $this->reason],
        ]], 409);
    }
}
