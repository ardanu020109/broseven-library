@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    <h2 class="auth-title">Buat Password Baru</h2>
    <p class="text-muted mb-4">Masukkan password baru untuk akun Anda.</p>

    <form method="POST" action="{{ route('password.update') }}" data-once>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password Baru</label>
            <div class="input-group has-validation">
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required autofocus>
                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Tampilkan password">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text">Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-shield-lock me-1"></i>Simpan Password Baru
        </button>
    </form>
@endsection
