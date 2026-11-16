@extends('layouts.auth')

@section('title', 'Lupa Password')

@section('content')
    <h2 class="auth-title">Lupa Password?</h2>
    <p class="text-muted mb-4">Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat password baru.</p>

    <form method="POST" action="{{ route('password.email') }}" data-once>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-send me-1"></i>Kirim Tautan Reset
        </button>
    </form>

    <p class="auth-footer-link text-center mt-4 mb-0">
        Ingat password Anda? <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
@endsection
