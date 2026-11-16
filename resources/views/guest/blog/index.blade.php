@extends('layouts.guest')

@section('title', 'Blog')
@section('meta_description', 'Artikel, tips membaca, dan kabar terbaru dari ' . $libraryProfile->name)

@section('content')
<section class="page-header">
    <div class="container">
        <h1>Blog</h1>
        <p>Kabar, tips, dan resensi dari perpustakaan.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                @if ($term !== '' || $activeCategory !== '')
                    <p class="text-muted small">
                        Hasil untuk
                        @if ($term !== '') kata kunci <strong>{{ $term }}</strong>@endif
                        @if ($activeCategory !== '') kategori <strong>{{ $categories->firstWhere('slug', $activeCategory)?->name ?? $activeCategory }}</strong>@endif
                        · <a href="{{ route('blog.index') }}">Hapus filter</a>
                    </p>
                @endif

                <div class="row g-4">
                    @forelse ($posts as $post)
                        <div class="col-md-6">
                            <x-blog-card :post="$post" />
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="bi bi-newspaper"></i>
                                <p class="mb-0">Belum ada artikel yang sesuai.</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    {{ $posts->links() }}
                </div>
            </div>

            <aside class="col-lg-4">
                @include('guest.blog._sidebar')
            </aside>
        </div>
    </div>
</section>
@endsection
