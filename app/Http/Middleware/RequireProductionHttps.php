<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireProductionHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->isSecure()) {
            return response()->json(['error' => [
                'code' => 'https_required',
                'message' => 'A secure HTTPS connection is required.',
                'requestId' => $request->attributes->get('request_id'),
                'fieldErrors' => null,
                'conflict' => null,
            ]], 426);
        }

        return $next($request);
    }
}
