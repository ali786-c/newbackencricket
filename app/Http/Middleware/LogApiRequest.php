<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        Log::info('api_request_completed', [
            'request_id' => $request->attributes->get('request_id'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return $response;
    }
}
