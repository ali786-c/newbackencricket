<?php

namespace App\Domains\Teams\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class TeamOwnershipTransferConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The ownership transfer cannot be completed in its current state.');
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        return response()->json(['error' => [
            'code' => 'team_ownership_transfer_conflict',
            'message' => $this->getMessage(),
            'requestId' => is_string($requestId) && Str::isUlid($requestId) ? Str::upper($requestId) : (string) Str::ulid(),
            'conflict' => ['reason' => $this->reason],
        ]], 409);
    }
}
