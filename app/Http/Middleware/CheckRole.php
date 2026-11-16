<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Pemakaian di route: ->middleware('role:super-admin') atau 'role:admin-master,admin-karyawan'.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $user->hasRole(...$roles)) {
            ActivityLog::record(
                'auth.forbidden',
                'Akses ditolak ke /' . $request->path(),
                null,
                ['required_roles' => $roles, 'method' => $request->method()],
            );

            abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
        }

        return $next($request);
    }
}
