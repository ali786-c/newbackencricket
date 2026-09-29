<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Sync\Actions\IngestSyncOperationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sync\StoreSyncOperationRequest;
use Illuminate\Http\JsonResponse;

class SyncOutboxController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreSyncOperationRequest $request,
        IngestSyncOperationAction $ingest,
    ): JsonResponse {
        $result = $ingest->handle($request->user(), $request->validated());
        $operation = $result['operation'];

        return response()->json([
            'data' => [
                'outbox_id' => $operation->outbox_id,
                'status' => $operation->status,
                'duplicate' => $result['duplicate'],
                'accepted_at' => $operation->accepted_at->toISOString(),
            ],
        ], $result['duplicate'] ? 200 : 202);
    }
}
