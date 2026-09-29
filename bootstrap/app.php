<?php

use App\Domains\Matches\Exceptions\MatchIngestionConflict;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\MonitorSyncHealth;
use App\Http\Middleware\RequireProductionHttps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->api(prepend: [AssignRequestId::class, RequireProductionHttps::class, LogApiRequest::class, MonitorSyncHealth::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof MatchIngestionConflict) {
                return response()->json(['error' => ['code' => $exception->category, 'message' => $exception->getMessage(), 'requestId' => $request->attributes->get('request_id'), 'fieldErrors' => null, 'conflict' => $exception->metadata]], 409);
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found',
                409 => 'conflict', 429 => 'rate_limited', default => 'server_error',
            };
            $message = match ($status) {
                401 => 'Authentication is required.', 403 => 'This action is not allowed.',
                404 => 'The requested resource was not found.', 409 => 'The request conflicts with current state.',
                429 => 'Too many requests. Please try again later.', default => 'The server could not complete the request.',
            };

            return response()->json(['error' => [
                'code' => $code, 'message' => $message,
                'requestId' => $request->attributes->get('request_id'),
                'fieldErrors' => null, 'conflict' => null,
            ]], $status);
        });
    })->create();
