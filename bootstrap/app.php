<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON errors for AJAX/fetch requests
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->wantsJson()) {
                \Illuminate\Support\Facades\Log::error('[GLOBAL EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);

                return response()->json([
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => basename($e->getFile()) . ':' . $e->getLine(),
                ], method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
            }
        });
    })->create();
