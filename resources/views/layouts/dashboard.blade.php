@php
    $authUser = auth()->user();
    $menuGroups = \App\Support\Menu::for($authUser->role);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') — {{ $libraryProfile->name }}</title>

    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    @stack('styles')
</head>
<body class="dash-body">
<div class="dash-wrapper">

    {{-- SIDEBAR --}}
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="sidebar-inner">
            <div class="sidebar-brand">
                <a href="{{ route($authUser->role->dashboardRoute()) }}" class="sidebar-logo" id="sidebarLabel">
                    @if ($libraryProfile->logo)
                        <img src="{{ $libraryProfile->logo_url }}" alt="" height="30">
                    @else
                        <i class="bi bi-book-half"></i>
                    @endif
                    <span>{{ $libraryProfile->name }}</span>
                </a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                        data-bs-target="#sidebar" aria-label="Tutup menu"></button>
            </div>

            <div class="sidebar-role">
                <i class="bi @yield('panel_icon', 'bi-person-badge')"></i>
                <span>@yield('panel_label', $authUser->role->label())</span>
            </div>

            <nav class="sidebar-nav" aria-label="Menu utama">
                @foreach ($menuGroups as $group)
                    <div class="side-group">
                        @if ($group['title'])
                            <div class="side-group-title">{{ $group['title'] }}</div>
                        @endif

                        @foreach ($group['items'] as $item)
                            @if ($item['enabled'])
                                <a href="{{ $item['url'] }}" class="side-link {{ $item['active'] ? 'active' : '' }}"
                                   @if ($item['active']) aria-current="page" @endif>
                                    <i class="bi {{ $item['icon'] }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @else
                                <span class="side-link is-disabled" title="Modul ini dikerjakan pada tahap berikutnya" aria-disabled="true">
                                    <i class="bi {{ $item['icon'] }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                    <small class="side-soon">Segera</small>
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="sidebar-footer">
                <a href="{{ route('home') }}" class="side-link">
                    <i class="bi bi-box-arrow-up-right"></i><span>Lihat Website</span>
                </a>
            </div>
        </div>
    </aside>

    {{-- KONTEN --}}
    <div class="dash-main">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-primary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="topbar-title">@yield('page_title', 'Dashboard')</span>
            </div>

            <div class="dropdown">
                <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="{{ $authUser->photo_url }}" alt="" class="user-avatar">
                    <span class="d-none d-md-flex flex-column text-start lh-sm">
                        <span class="user-name">{{ $authUser->name }}</span>
                        <span class="user-role">{{ $authUser->role->label() }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="px-3 py-2 d-md-none">
                        <div class="fw-semibold">{{ $authUser->name }}</div>
                        <div class="small text-muted">{{ $authUser->role->label() }}</div>
                    </li>
                    <li class="d-md-none"><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-house me-2"></i>Lihat Website</a></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="dash-content">
            <x-flash-messages />
            @yield('content')
        </main>

        <footer class="dash-footer">
            &copy; {{ date('Y') }} {{ $libraryProfile->name }}
        </footer>
    </div>
</div>

<script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
@stack('scripts')
</body>
</html>
