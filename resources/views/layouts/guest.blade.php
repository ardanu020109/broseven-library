@php
    $metaDefault = Str::limit(strip_tags((string) $libraryProfile->description), 150);

    $navLinks = [
        ['route' => 'home', 'pattern' => 'home', 'label' => 'Beranda'],
        ['route' => 'about', 'pattern' => 'about', 'label' => 'Tentang Kami'],
        ['route' => 'catalog.index', 'pattern' => 'catalog.*', 'label' => 'Katalog Buku'],
        ['route' => 'blog.index', 'pattern' => 'blog.*', 'label' => 'Blog'],
        ['route' => 'contact', 'pattern' => 'contact', 'label' => 'Kontak'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', $metaDefault)">

    <title>@yield('title', 'Beranda') — {{ $libraryProfile->name }}</title>

    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/guest.css') }}">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-bsl sticky-top" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            @if ($libraryProfile->logo)
                <img src="{{ $libraryProfile->logo_url }}" alt="" height="32">
            @else
                <i class="bi bi-book-half"></i>
            @endif
            <span class="fw-bold">{{ $libraryProfile->name }}</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                @foreach ($navLinks as $link)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($link['pattern']) ? 'active fw-semibold' : '' }}"
                           href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>

            <div class="d-flex flex-column flex-lg-row gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">
                            <i class="bi bi-box-arrow-right me-1"></i>Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-light btn-sm text-crimson fw-semibold">
                        <i class="bi bi-person-plus me-1"></i>Daftar Anggota
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

@if (session()->hasAny(['status', 'success', 'error', 'warning']))
    <div class="container pt-3">
        <x-flash-messages />
    </div>
@endif

<main class="flex-grow-1">
    @yield('content')
</main>

<footer class="guest-footer">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    @if ($libraryProfile->logo)
                        <img src="{{ $libraryProfile->logo_url }}" alt="" height="36">
                    @else
                        <i class="bi bi-book-half fs-3"></i>
                    @endif
                    <span class="fs-5 fw-bold">{{ $libraryProfile->name }}</span>
                </div>
                <p class="footer-text">{{ Str::limit(strip_tags((string) $libraryProfile->description), 160) }}</p>
                @include('guest.partials.social-links')
            </div>

            <div class="col-6 col-lg-2 offset-lg-1">
                <h2 class="footer-heading">Menu</h2>
                <ul class="list-unstyled footer-links">
                    @foreach ($navLinks as $link)
                        <li><a href="{{ route($link['route']) }}">{{ $link['label'] }}</a></li>
                    @endforeach
                    @guest
                        <li><a href="{{ route('login') }}">Masuk</a></li>
                        <li><a href="{{ route('register') }}">Daftar Anggota</a></li>
                    @endguest
                </ul>
            </div>

            <div class="col-lg-5">
                <h2 class="footer-heading">Hubungi Kami</h2>
                <ul class="list-unstyled footer-contact">
                    @if ($libraryProfile->address)
                        <li><i class="bi bi-geo-alt"></i><span>{{ $libraryProfile->address }}</span></li>
                    @endif
                    @if ($libraryProfile->phone)
                        <li><i class="bi bi-telephone"></i>
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $libraryProfile->phone) }}">{{ $libraryProfile->phone }}</a></li>
                    @endif
                    @if ($libraryProfile->email)
                        <li><i class="bi bi-envelope"></i>
                            <a href="mailto:{{ $libraryProfile->email }}">{{ $libraryProfile->email }}</a></li>
                    @endif
                    @if ($libraryProfile->operating_hours)
                        <li><i class="bi bi-clock"></i><span>{!! nl2br(e($libraryProfile->operating_hours)) !!}</span></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container py-3 d-flex flex-column flex-md-row justify-content-between gap-1 small">
            <span>&copy; {{ date('Y') }} {{ $libraryProfile->name }}. Hak cipta dilindungi.</span>
            @if ($libraryProfile->website)
                <span>{{ preg_replace('#^https?://#', '', $libraryProfile->website) }}</span>
            @endif
        </div>
    </div>
</footer>

<script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/guest.js') }}"></script>
@stack('scripts')
</body>
</html>
