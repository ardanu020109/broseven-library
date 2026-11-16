@extends('layouts.guest')

@section('title', $blog->title)
@section('meta_description', Str::limit($blog->excerpt ?: strip_tags($blog->content), 150))

@section('content')
<section class="page-header page-header-sm">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Blog</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($blog->title, 40) }}</li>
            </ol>
        </nav>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <article class="col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-muted">
                    @if ($blog->category)
                        <a href="{{ route('blog.index', ['kategori' => $blog->category->slug]) }}" class="badge-category">{{ $blog->category->name }}</a>
                    @endif
                    <span><i class="bi bi-calendar3 me-1"></i>{{ $blog->published_at?->translatedFormat('d F Y') }}</span>
                    <span><i class="bi bi-person me-1"></i>{{ $blog->author->name }}</span>
                </div>

                <h1 class="h2 mb-4">{{ $blog->title }}</h1>

                @if ($blog->thumbnail)
                    <img src="{{ $blog->thumbnail_url }}" alt="" class="article-image mb-4">
                @endif

                <div class="article-body">
                    @foreach ($paragraphs as $paragraph)
                        <p>{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach
                </div>

                <hr class="my-5">
                <a href="{{ route('blog.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar artikel
                </a>
            </article>

            <aside class="col-lg-4">
                @include('guest.blog._sidebar')
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <hr class="my-5">
            <h2 class="section-title">Artikel Terkait</h2>
            <div class="row g-4 mt-1">
                @foreach ($related as $post)
                    <div class="col-md-6 col-lg-4">
                        <x-blog-card :post="$post" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
