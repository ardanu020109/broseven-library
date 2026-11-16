@extends('layouts.guest')

@section('title', 'Beranda')

@section('content')

{{-- 1. HERO --}}
<section class="hero bg-brand-gradient">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-eyebrow">Selamat datang di</span>
                <h1 class="hero-title">{{ $libraryProfile->name }}</h1>
                @if ($libraryProfile->tagline)
                    <p class="hero-tagline">{{ $libraryProfile->tagline }}</p>
                @endif
                @if ($libraryProfile->description)
                    <p class="hero-desc">{{ Str::limit(strip_tags($libraryProfile->description), 220) }}</p>
                @endif

                <form action="{{ route('catalog.index') }}" method="GET" class="hero-search" role="search">
                    <input type="search" name="q" class="form-control form-control-lg"
                           placeholder="Cari judul buku atau ISBN" aria-label="Cari buku" maxlength="100">
                    <button type="submit" class="btn btn-accent btn-lg" aria-label="Cari">
                        <i class="bi bi-search"></i>
                    </button>
                </form>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('catalog.index') }}" class="btn btn-light btn-lg">
                        <i class="bi bi-compass me-1"></i>Jelajahi Buku
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-speedometer2 me-1"></i>Buka Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-person-plus me-1"></i>Daftar Anggota
                        </a>
                    @endauth
                </div>
            </div>

            <div class="col-lg-6 text-center d-none d-md-block">
                <img src="{{ asset('assets/images/hero-books.svg') }}" alt="Ilustrasi rak buku perpustakaan" class="hero-illustration">
            </div>
        </div>
    </div>
</section>

{{-- 2. STATISTIK --}}
<section class="stats-strip">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <i class="bi bi-journal-bookmark stat-icon"></i>
                    <div class="stat-value">{{ number_format($stats['titles'], 0, ',', '.') }}</div>
                    <div class="stat-label">Judul Buku</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <i class="bi bi-collection stat-icon"></i>
                    <div class="stat-value">{{ number_format($stats['copies'], 0, ',', '.') }}</div>
                    <div class="stat-label">Total Eksemplar</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-value">{{ number_format($stats['members'], 0, ',', '.') }}</div>
                    <div class="stat-label">Anggota Aktif</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <i class="bi bi-tags stat-icon"></i>
                    <div class="stat-value">{{ number_format($stats['categories'], 0, ',', '.') }}</div>
                    <div class="stat-label">Kategori</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 3. BUKU TERBARU --}}
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2 class="section-title">Buku Terbaru</h2>
                <p class="section-sub">Koleksi yang baru masuk ke perpustakaan.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary btn-sm">
                Lihat semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            @forelse ($latestBooks as $book)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-book-card :book="$book" />
                </div>
            @empty
                <div class="col-12"><p class="empty-state">Belum ada buku yang ditampilkan.</p></div>
            @endforelse
        </div>
    </div>
</section>

{{-- 4. BUKU POPULER --}}
<section class="section section-beige">
    <div class="container">
        <div class="section-head">
            <div>
                <h2 class="section-title">Buku Populer</h2>
                <p class="section-sub">Judul yang paling sering dipinjam anggota.</p>
            </div>
            <a href="{{ route('catalog.index', ['sort' => 'popular']) }}" class="btn btn-outline-primary btn-sm">
                Lihat semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            @forelse ($popularBooks as $book)
                <div class="col-6 col-lg-3">
                    <x-book-card :book="$book" />
                </div>
            @empty
                <div class="col-12"><p class="empty-state">Belum ada buku yang ditampilkan.</p></div>
            @endforelse
        </div>
    </div>
</section>

{{-- 5. KATEGORI --}}
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2 class="section-title">Kategori Buku</h2>
                <p class="section-sub">Temukan bacaan sesuai minat Anda.</p>
            </div>
        </div>

        <div class="row g-3">
            @forelse ($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('catalog.index', ['category' => $category->slug]) }}" class="category-card">
                        <i class="bi bi-bookmark-star"></i>
                        <span class="category-name">{{ $category->name }}</span>
                        <span class="category-count">{{ $category->books_count }} buku</span>
                    </a>
                </div>
            @empty
                <div class="col-12"><p class="empty-state">Belum ada kategori.</p></div>
            @endforelse
        </div>
    </div>
</section>

{{-- 6. TENTANG PERPUSTAKAAN --}}
<section class="section section-beige">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="section-title">Tentang Perpustakaan</h2>
                @if ($libraryProfile->description)
                    <p>{!! nl2br(e(Str::limit(strip_tags($libraryProfile->description), 480))) !!}</p>
                @endif
                <a href="{{ route('about') }}" class="btn btn-primary">
                    Selengkapnya <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="h6 text-sienna mb-3"><i class="bi bi-journal-check me-2"></i>Aturan Peminjaman Singkat</h3>
                        <ul class="rule-list mb-0">
                            <li><i class="bi bi-check2-circle"></i>Maksimal <strong>{{ $libraryProfile->max_books_per_loan }}</strong> buku sekali pinjam</li>
                            <li><i class="bi bi-check2-circle"></i>Lama peminjaman <strong>{{ $libraryProfile->loan_duration_days }}</strong> hari</li>
                            <li><i class="bi bi-check2-circle"></i>Denda keterlambatan
                                <strong>Rp{{ number_format((float) $libraryProfile->fine_per_day, 0, ',', '.') }}</strong> per hari per buku</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 7. ARTIKEL BLOG TERBARU --}}
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2 class="section-title">Artikel Terbaru</h2>
                <p class="section-sub">Kabar, tips, dan resensi dari perpustakaan.</p>
            </div>
            <a href="{{ route('blog.index') }}" class="btn btn-outline-primary btn-sm">
                Semua artikel <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            @forelse ($latestPosts as $post)
                <div class="col-md-6 col-lg-4">
                    <x-blog-card :post="$post" />
                </div>
            @empty
                <div class="col-12"><p class="empty-state">Belum ada artikel yang dipublikasikan.</p></div>
            @endforelse
        </div>
    </div>
</section>

{{-- 8. INFORMASI KONTAK --}}
<section class="section section-beige">
    <div class="container">
        <div class="section-head">
            <div>
                <h2 class="section-title">Informasi Kontak</h2>
                <p class="section-sub">Kami siap membantu Anda.</p>
            </div>
            <a href="{{ route('contact') }}" class="btn btn-outline-primary btn-sm">Halaman kontak</a>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <div class="info-card"><i class="bi bi-geo-alt"></i>
                    <h3>Alamat</h3><p>{{ $libraryProfile->address ?: '-' }}</p></div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-card"><i class="bi bi-telephone"></i>
                    <h3>Telepon</h3>
                    <p>
                        @if ($libraryProfile->phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $libraryProfile->phone) }}">{{ $libraryProfile->phone }}</a>
                        @else - @endif
                    </p></div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-card"><i class="bi bi-envelope"></i>
                    <h3>Email</h3>
                    <p>
                        @if ($libraryProfile->email)
                            <a href="mailto:{{ $libraryProfile->email }}">{{ $libraryProfile->email }}</a>
                        @else - @endif
                    </p></div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="info-card"><i class="bi bi-clock"></i>
                    <h3>Jam Operasional</h3>
                    <p>{!! $libraryProfile->operating_hours ? nl2br(e($libraryProfile->operating_hours)) : '-' !!}</p></div>
            </div>
        </div>
    </div>
</section>

@endsection
