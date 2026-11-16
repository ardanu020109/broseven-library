<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /** Status dari password broker (kunci terjemahan Laravel). */
    private const STATUS_THROTTLED = 'passwords.throttled';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === self::STATUS_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Terlalu sering meminta tautan. Coba lagi beberapa saat lagi.']);
        }

        // Pesan sama untuk email terdaftar maupun tidak, agar akun tidak bisa ditebak.
        return back()->with('status', 'Jika email terdaftar, tautan reset password telah dikirim. Periksa kotak masuk Anda.');
    }
}
