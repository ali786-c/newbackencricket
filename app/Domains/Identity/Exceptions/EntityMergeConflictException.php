<?php

namespace App\Domains\Identity\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class EntityMergeConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The entity merge cannot be completed safely.');
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        return response()->json(['error' => [
            'code' => 'entity_merge_conflict',
            'message' => $this->getMessage(),
            'requestId' => is_string($requestId) && Str::isUlid($requestId) ? Str::upper($requestId) : (string) Str::ulid(),
            'conflict' => ['reason' => $this->reason],
        ]], 409);
    }
}
