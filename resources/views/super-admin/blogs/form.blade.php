@extends('layouts.super-admin')

@section('title', $blog->exists ? 'Ubah Artikel' : 'Tambah Artikel')
@section('page_title', 'Blog')

@section('content')
<x-page-header :title="$blog->exists ? 'Ubah Artikel' : 'Tambah Artikel'" subtitle="Kolom bertanda * wajib diisi.">
    <a href="{{ $blog->exists ? route('super-admin.blogs.show', $blog) : route('super-admin.blogs.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</x-page-header>

<form method="POST" enctype="multipart/form-data" data-once
      action="{{ $blog->exists ? route('super-admin.blogs.update', $blog) : route('super-admin.blogs.store') }}">
    @csrf
    @if ($blog->exists)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-newspaper me-2"></i>Isi Artikel</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="title" label="Judul" :value="$blog->title" :required="true" maxlength="200" />
                        <x-form-field name="excerpt" label="Ringkasan" type="textarea" :value="$blog->excerpt" rows="2" maxlength="300"
                                      help="Tampil di kartu artikel. Kosong = diambil dari awal isi artikel." />
                        <x-form-field name="content" label="Isi Artikel" type="textarea" :value="$blog->content" :required="true" rows="16"
                                      help="Teks biasa. Pisahkan paragraf dengan satu baris kosong." />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-gear me-2"></i>Pengaturan</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-select name="status" label="Status" :required="true"
                                       :options="['draft' => 'Draft (tidak tampil)', 'published' => 'Terbit']"
                                       :value="$blog->status" placeholder="-- Pilih status --" />

                        <div class="col-12">
                            <label for="published_at" class="form-label fw-semibold">Tanggal Publikasi</label>
                            <input type="datetime-local" id="published_at" name="published_at"
                                   value="{{ old('published_at', $blog->published_at?->format('Y-m-d\TH:i')) }}"
                                   class="form-control @error('published_at') is-invalid @enderror">
                            @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Hanya untuk status Terbit. Kosong = sekarang. Tanggal masa depan = terjadwal.</div>
                        </div>

                        <div class="col-12">
                            <label for="category_name" class="form-label fw-semibold">Kategori</label>
                            <input type="text" id="category_name" name="category_name" list="categoryList" maxlength="100"
                                   value="{{ old('category_name', $blog->category?->name) }}"
                                   class="form-control @error('category_name') is-invalid @enderror" autocomplete="off">
                            <datalist id="categoryList">
                                @foreach ($categoryNames as $name)
                                    <option value="{{ $name }}"></option>
                                @endforeach
                            </datalist>
                            @error('category_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Pilih dari saran atau ketik nama baru (dibuat otomatis).</div>
                        </div>

                        <x-form-field name="slug" label="Slug" :value="$blog->slug" maxlength="220"
                                      help="Kosong = dibuat otomatis dari judul." />
                    </div>
                </div>
            </div>

            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-image me-2"></i>Thumbnail</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-image name="thumbnail" label="Gambar Thumbnail" :current="$blog->thumbnail ? $blog->thumbnail_url : null"
                                      :removable="(bool) $blog->thumbnail" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a href="{{ $blog->exists ? route('super-admin.blogs.show', $blog) : route('super-admin.blogs.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection
