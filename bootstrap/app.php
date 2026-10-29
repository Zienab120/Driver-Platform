<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureDriverApproved;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureRole::class,
            'account.active' => EnsureAccountActive::class,
            'driver.approved' => EnsureDriverApproved::class,
        ]);
        // Behind a load balancer/CDN, trust it so $request->ip() is the real client (needed for per-IP OTP limits):
        // $middleware->trustProxies(at: ['10.0.0.0/8']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn(Request $r) => true);

        $exceptions->render(fn(ValidationException $e) => response()->json([
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'The given data was invalid.', 'details' => $e->errors()],
        ], 422));

        $exceptions->render(fn(AuthenticationException $e) => response()->json([
            'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentication required.'],
        ], 401));

        $exceptions->render(fn(ThrottleRequestsException $e) => response()->json([
            'error' => [
                'code' => 'RATE_LIMITED',
                'message' => 'Too many requests.',
                'retry_after_seconds' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
            ],
        ], 429, $e->getHeaders()));
    })->create();
