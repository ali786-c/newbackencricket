<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MonitorSyncHealth
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->is('api/v1/sync/*', 'api/v1/matches/*/events/*', 'api/v1/matches/*/corrections')) {
            return $response;
        }

        $status = $response->getStatusCode();
        $category = $status < 400 ? 'accepted' : ($status === 409 ? 'conflict' : 'rejected');
        DB::table('sync_health_events')->insert([
            'id' => (string) Str::ulid(),
            'category' => $category,
            'operation' => Str::limit($request->route()?->getName() ?? $request->path(), 120, ''),
            'http_status' => $status,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'device_id_hash' => $request->header('X-Device-ID') === null
                ? null
                : hash('sha256', (string) $request->header('X-Device-ID')),
            'created_at' => now('UTC'),
        ]);

        return $response;
    }
}
