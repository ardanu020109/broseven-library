@extends('layouts.super-admin')

@section('title', 'Pratinjau: ' . $blog->title)
@section('page_title', 'Blog')

@section('content')
@php
    $scheduled = $blog->status === 'published' && $blog->published_at && $blog->published_at->isFuture();
    $live = $blog->status === 'published' && ! $scheduled;
@endphp

<x-page-header title="Pratinjau Artikel" :subtitle="$blog->title">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('super-admin.blogs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <a href="{{ route('super-admin.blogs.edit', $blog) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>

        @if ($live)
            <a href="{{ route('blog.show', $blog->slug) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Lihat di Website
            </a>
        @endif

        @if (! $live)
            <form method="POST" action="{{ route('super-admin.blogs.publish', $blog) }}">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-cloud-upload me-1"></i>Publikasikan Sekarang</button>
            </form>
        @endif
        @if ($blog->status === 'published')
            <form method="POST" action="{{ route('super-admin.blogs.unpublish', $blog) }}">
                @csrf
                <button type="submit" class="btn btn-outline-warning"><i class="bi bi-cloud-download me-1"></i>Tarik dari Publik</button>
            </form>
        @endif

        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                data-delete-url="{{ route('super-admin.blogs.destroy', $blog) }}" data-delete-name="{{ $blog->title }}">
            <i class="bi bi-trash me-1"></i>Hapus
        </button>
    </div>
</x-page-header>

@if ($blog->status === 'draft')
    <div class="alert alert-secondary"><i class="bi bi-eye-slash me-2"></i><strong>Draft.</strong> Artikel ini belum tampil di website.</div>
@elseif ($scheduled)
    <div class="alert alert-warning"><i class="bi bi-calendar-event me-2"></i><strong>Terjadwal.</strong> Tampil di website pada {{ $blog->published_at->translatedFormat('d F Y H:i') }}.</div>
@else
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><strong>Terbit</strong> sejak {{ $blog->published_at->translatedFormat('d F Y H:i') }}.</div>
@endif

<div class="card dash-card">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-muted">
            @if ($blog->category)<span class="badge-category">{{ $blog->category->name }}</span>@endif
            <span><i class="bi bi-person me-1"></i>{{ $blog->author->name }}</span>
            <span><i class="bi bi-link-45deg me-1"></i>/blog/{{ $blog->slug }}</span>
        </div>

        <h2 class="h3 mb-3">{{ $blog->title }}</h2>

        @if ($blog->thumbnail)
            <img src="{{ $blog->thumbnail_url }}" alt="" class="img-fluid rounded mb-3" style="max-height:360px;object-fit:cover">
        @endif

        <div class="article-preview">
            @foreach ($paragraphs as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>
    </div>
</div>

<x-delete-modal />
@endsection
