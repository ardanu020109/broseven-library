@extends('layouts.auth')

@section('title', 'Daftar Anggota')
@section('panel_class', 'auth-panel-wide')

@section('content')
    <h2 class="auth-title">Daftar Anggota Perpustakaan</h2>
    <p class="text-muted mb-4">Buat akun untuk meminjam buku dan mendapatkan kartu anggota digital.</p>

    <form method="POST" action="{{ route('register.store') }}" data-once>
        @csrf

        <div class="row g-3">
            <div class="col-12">
                <label for="name" class="form-label">Nama Lengkap</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="form-control @error('name') is-invalid @enderror" autocomplete="name" required autofocus>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username') }}"
                       class="form-control @error('username') is-invalid @enderror" autocomplete="username" required>
                <div class="form-text">Huruf, angka, strip, dan garis bawah. Minimal 3 karakter.</div>
                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="phone" class="form-label">Nomor Telepon <span class="text-muted">(opsional)</span></label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                       class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="address" class="form-label">Alamat <span class="text-muted">(opsional)</span></label>
                <input type="text" id="address" name="address" value="{{ old('address') }}"
                       class="form-control @error('address') is-invalid @enderror" autocomplete="street-address">
                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <div class="input-group has-validation">
                    <input type="password" id="password" name="password"
                           class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Tampilkan password">
                        <i class="bi bi-eye"></i>
                    </button>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-text">Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.</div>
            </div>

            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                <div class="input-group">
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-control" autocomplete="new-password" required>
                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password_confirmation" aria-label="Tampilkan password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mt-4">
            <i class="bi bi-person-plus me-1"></i>Daftar
        </button>
    </form>

    <p class="auth-footer-link text-center mt-4 mb-0">
        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
    </p>
@endsection
