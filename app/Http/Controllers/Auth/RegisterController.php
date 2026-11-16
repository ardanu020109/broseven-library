<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ActivityLog;
use App\Services\MemberService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, MemberService $members): RedirectResponse
    {
        $user = $members->register($request->validated());

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::record('auth.register', 'Registrasi anggota baru', $user);

        return redirect()
            ->route($user->role->dashboardRoute())
            ->with('status', 'Registrasi berhasil. Selamat datang di ' . config('app.name') . '!');
    }
}
