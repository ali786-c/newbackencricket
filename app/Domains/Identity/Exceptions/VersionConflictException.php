<?php

namespace App\Domains\Identity\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class VersionConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly int $currentVersion,
    ) {
        parent::__construct('The entity changed after the submitted base version.');
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        return response()->json(['error' => [
            'code' => 'version_conflict',
            'message' => $this->getMessage(),
            'requestId' => is_string($requestId) && Str::isUlid($requestId) ? Str::upper($requestId) : (string) Str::ulid(),
            'conflict' => [
                'entityType' => $this->entityType,
                'entityId' => $this->entityId,
                'currentVersion' => $this->currentVersion,
            ],
        ]], 409);
    }
}
