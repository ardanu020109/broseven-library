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

        // Tamu yang membuka halaman terlindungi diarahkan ke login.
        $middleware->redirectGuestsTo(fn(Request $request) => route('login'));

        // Pengguna yang sudah login dan membuka halaman tamu (login/register)
        // diarahkan ke dashboard sesuai rolenya.
        $middleware->redirectUsersTo(function (Request $request) {
            return route($request->user()->role->dashboardRoute());
        });

        // Header keamanan dan pembatas laju untuk semua route web.
        $middleware->web(append: [SecurityHeaders::class, 'throttle:web']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
