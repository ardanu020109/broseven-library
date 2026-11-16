<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Masuk') — {{ $libraryProfile->name ?? config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
</head>

<body class="auth-body">
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">

            {{-- Panel merek (desktop) --}}
            <aside class="col-lg-5 d-none d-lg-flex auth-brand">
                <div class="auth-brand-inner">
                    <i class="bi bi-book-half auth-brand-icon"></i>
                    <h1>{{ $libraryProfile->name ?? config('app.name') }}</h1>
                    @if (!empty($libraryProfile->tagline))
                        <p class="auth-brand-tagline">{{ $libraryProfile->tagline }}</p>
                    @endif

                    <ul class="auth-brand-list">
                        <li><i class="bi bi-search"></i> Cari dan telusuri koleksi buku</li>
                        <li><i class="bi bi-journal-check"></i> Ajukan peminjaman secara online</li>
                        <li><i class="bi bi-person-vcard"></i> Kartu anggota digital</li>
                    </ul>
                </div>
            </aside>

            {{-- Panel form --}}
            <main class="col-lg-7 d-flex align-items-center justify-content-center p-4 auth-main">
                <div class="auth-panel @yield('panel_class')">
                    <div class="auth-mobile-brand d-lg-none">
                        <i class="bi bi-book-half"></i>
                        <span>{{ $libraryProfile->name ?? config('app.name') }}</span>
                    </div>

                    <x-flash-messages />

                    @yield('content')

                    <p class="auth-footer-link text-center mt-4 mb-0">
                        <a href="{{ url('/') }}"><i class="bi bi-arrow-left me-1"></i>Kembali ke beranda</a>
                    </p>
                </div>
            </main>

        </div>
    </div>

    <script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/auth.js') }}"></script>
    @stack('scripts')
</body>

</html>
