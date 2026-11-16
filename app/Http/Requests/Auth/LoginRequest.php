<?php

namespace App\Http\Requests\Auth;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Login memakai username ATAU email. Pesan error sengaja tidak
     * membedakan "akun tidak ada" dan "password salah".
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('login')->toString());
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            ActivityLog::record('auth.login_failed', 'Percobaan login gagal', null, ['login' => $login]);

            throw ValidationException::withMessages([
                'login' => 'Username/email atau password salah.',
            ]);
        }

        // Password benar tetapi akun dinonaktifkan: tolak dan tutup sesi.
        if (! Auth::user()->is_active) {
            $userId = Auth::id();

            Auth::guard('web')->logout();

            ActivityLog::record('auth.login_blocked', 'Login ditolak: akun nonaktif', null, ['login' => $login], $userId);

            throw ValidationException::withMessages([
                'login' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')->toString()) . '|' . $this->ip());
    }
}
