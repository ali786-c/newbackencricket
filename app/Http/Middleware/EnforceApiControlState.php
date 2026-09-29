<?php

namespace App\Http\Middleware;

use App\Support\ApiControlState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiControlState
{
    public function handle(Request $request, Closure $next): Response
    {
        $state = ApiControlState::current();
        if (! $state['registration_enabled'] && $request->is('api/v1/auth/register')) {
            return $this->unavailable('registration_disabled', 'New account registration is temporarily disabled.');
        }
        $allowedMutation = $request->is('api/v1/auth/login', 'api/v1/auth/logout', 'api/v1/auth/logout-all');
        if ($state['api_read_only'] && ! $request->isMethodSafe() && ! $allowedMutation) {
            return $this->unavailable('api_read_only', 'The API is temporarily in read-only mode.');
        }

        return $next($request);
    }

    private function unavailable(string $code, string $message): Response
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'requestId' => request()->attributes->get('request_id'), 'fieldErrors' => null, 'conflict' => null]], 503);
    }
}
