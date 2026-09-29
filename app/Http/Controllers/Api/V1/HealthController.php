<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiControlState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'service' => 'stumps-api']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json([
                'status' => 'ready',
                'dependencies' => ['database' => 'ok'],
                'rolloutStage' => ApiControlState::current()['rollout_stage'],
            ]);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable', 'dependencies' => ['database' => 'unavailable']], 503);
        }
    }
}
