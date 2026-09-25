<?php

namespace App\Domains\Sync\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class IdempotencyConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $operation, public readonly string $idempotencyKey)
    {
        parent::__construct('The idempotency key was already used with a different request payload.');
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        return response()->json(['error' => [
            'code' => 'idempotency_key_reused',
            'message' => $this->getMessage(),
            'requestId' => is_string($requestId) && Str::isUlid($requestId) ? Str::upper($requestId) : (string) Str::ulid(),
            'conflict' => ['operation' => $this->operation, 'idempotencyKey' => $this->idempotencyKey],
        ]], 409);
    }
}
