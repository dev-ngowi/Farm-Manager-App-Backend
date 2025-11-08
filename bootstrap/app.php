<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareMenus;
use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ShareMenus::class,
        ]);
        
        $middleware->alias([
            'menu.permission' => CheckMenuPermission::class,
        ]);

        // Configure stateful API if needed (for SPA using cookies)
        $middleware->statefulApi();
        
        // Handle unauthenticated redirects - return JSON for API routes
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Don't redirect API requests
            }
            return route('login'); // Redirect web requests to login
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle authentication exceptions for API routes
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Unauthenticated. Please provide a valid access token.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        });
    })->create();