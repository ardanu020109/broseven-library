@extends('layouts.auth')

@section('title', 'Akses Ditolak')

@section('content')
    <div class="text-center">
        <div class="error-code">403</div>
        <h2 class="auth-title mt-3">Akses Ditolak</h2>
        <p class="text-muted mb-4">
            {{ $exception->getMessage() ?: 'Anda tidak memiliki hak akses ke halaman ini.' }}
        </p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-speedometer2 me-1"></i>Kembali ke Dashboard Saya
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
            </a>
        @endauth
    </div>
@endsection
