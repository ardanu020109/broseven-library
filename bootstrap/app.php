<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'active' => EnsureUserIsActive::class,
        ]);
        $middleware->redirectGuestsTo(fn(Request $request) => route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            return route($request->user()->role->dashboardRoute());
        });

        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: ['throttle:web']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
