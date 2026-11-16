@extends('layouts.super-admin')

@section('title', 'Blog')
@section('page_title', 'Blog')

@section('content')
<x-page-header title="Manajemen Blog" :subtitle="$posts->total() . ' artikel ditemukan'">
    <a href="{{ route('super-admin.blogs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Artikel</a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('super-admin.blogs.index') }}" class="row g-2" role="search">
            <div class="col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"
                           placeholder="Cari judul atau ringkasan..." aria-label="Cari artikel">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select name="category" class="form-select" aria-label="Kategori" data-autosubmit>
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="status" class="form-select" aria-label="Status" data-autosubmit>
                    <option value="">Semua status</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="published" @selected($filters['status'] === 'published')>Terbit</option>
                    <option value="scheduled" @selected($filters['status'] === 'scheduled')>Terjadwal</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                @if ($hasFilters)
                    <a href="{{ route('super-admin.blogs.index') }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:3.5rem">No</th><th>Artikel</th><th>Kategori</th><th>Status</th><th>Tanggal Terbit</th><th class="text-end" style="width:12rem">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    @php
                        $scheduled = $post->status === 'published' && $post->published_at && $post->published_at->isFuture();
                    @endphp
                    <tr>
                        <td>{{ $posts->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $post->thumbnail_url }}" alt="" class="thumb" style="width:64px;height:40px">
                                <div>
                                    <a href="{{ route('super-admin.blogs.show', $post) }}" class="fw-semibold text-decoration-none">{{ $post->title }}</a>
                                    <div class="small text-muted">oleh {{ $post->author->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $post->category?->name ?? '-' }}</td>
                        <td>
                            @if ($post->status === 'draft')
                                <span class="badge text-bg-secondary">Draft</span>
                            @elseif ($scheduled)
                                <span class="badge badge-status badge-pending">Terjadwal</span>
                            @else
                                <span class="badge badge-status badge-available">Terbit</span>
                            @endif
                        </td>
                        <td class="text-nowrap small">{{ $post->published_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('super-admin.blogs.show', $post) }}" class="btn btn-sm btn-outline-secondary" title="Pratinjau" aria-label="Pratinjau"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('super-admin.blogs.edit', $post) }}" class="btn btn-sm btn-outline-primary" title="Ubah" aria-label="Ubah"><i class="bi bi-pencil"></i></a>

                            @if ($post->status === 'draft' || $scheduled)
                                <form method="POST" action="{{ route('super-admin.blogs.publish', $post) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Publikasikan sekarang" aria-label="Publikasikan"><i class="bi bi-cloud-upload"></i></button>
                                </form>
                            @endif
                            @if ($post->status === 'published')
                                <form method="POST" action="{{ route('super-admin.blogs.unpublish', $post) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Tarik dari publik" aria-label="Tarik dari publik"><i class="bi bi-cloud-download"></i></button>
                                </form>
                            @endif

                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-delete-url="{{ route('super-admin.blogs.destroy', $post) }}" data-delete-name="{{ $post->title }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">{{ $hasFilters ? 'Tidak ada artikel yang cocok dengan filter.' : 'Belum ada artikel.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($posts->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">{{ $posts->links() }}</div>
    @endif
</div>

<x-delete-modal />
@endsection
