@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <h2 class="auth-title">Masuk ke Akun Anda</h2>
    <p class="text-muted mb-4">Gunakan username atau email yang terdaftar.</p>

    <form method="POST" action="{{ route('login.store') }}" data-once>
        @csrf

        <div class="mb-3">
            <label for="login" class="form-label">Username atau Email</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="login" name="login" value="{{ old('login') }}"
                       class="form-control @error('login') is-invalid @enderror"
                       autocomplete="username" required autofocus>
                @error('login')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Tampilkan password">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Lupa password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
        </button>
    </form>

    <p class="auth-footer-link text-center mt-4 mb-0">
        Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai anggota</a>
    </p>
@endsection
