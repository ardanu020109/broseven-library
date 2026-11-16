@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', $book->exists ? 'Ubah Buku' : 'Tambah Buku')
@section('page_title', 'Buku')

@section('content')
<x-page-header :title="$book->exists ? 'Ubah Buku' : 'Tambah Buku'" subtitle="Kolom bertanda * wajib diisi.">
    <a href="{{ $book->exists ? Panel::route('books.show', $book) : Panel::route('books.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</x-page-header>

<form method="POST" enctype="multipart/form-data" data-once
      action="{{ $book->exists ? Panel::route('books.update', $book) : Panel::route('books.store') }}">
    @csrf
    @if ($book->exists)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Informasi Buku</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="title" label="Judul Buku" :value="$book->title" :required="true" maxlength="255" />
                        <x-form-field name="isbn" label="ISBN" :value="$book->isbn" maxlength="20" col="col-md-6"
                                      help="Opsional. Huruf, angka, dan strip." />
                        <x-form-field name="edition" label="Edisi" :value="$book->edition" maxlength="50" col="col-md-6" />

                        <x-form-select name="category_id" label="Kategori" :options="$categories" :value="$book->category_id"
                                       :required="true" col="col-md-4" help="Belum ada? Tambahkan di menu Kategori." />
                        <x-form-select name="author_id" label="Penulis" :options="$authors" :value="$book->author_id"
                                       :required="true" col="col-md-4" help="Belum ada? Tambahkan di menu Penulis." />
                        <x-form-select name="publisher_id" label="Penerbit" :options="$publishers" :value="$book->publisher_id"
                                       :required="true" col="col-md-4" help="Belum ada? Tambahkan di menu Penerbit." />

                        <x-form-select name="shelf_id" label="Rak Buku" :options="$shelves" :value="$book->shelf_id" col="col-md-8"
                                       placeholder="-- Tanpa rak --" />
                        <x-form-field name="shelf_number" label="Nomor Rak" :value="$book->shelf_number" maxlength="20" col="col-md-4" />

                        <x-form-field name="publication_year" label="Tahun Terbit" type="number" :value="$book->publication_year"
                                      min="1000" :max="date('Y') + 1" col="col-md-3" />
                        <x-form-field name="language" label="Bahasa" :value="$book->language" :required="true" maxlength="50" col="col-md-3" />
                        <x-form-field name="pages" label="Jumlah Halaman" type="number" :value="$book->pages" min="1" max="20000" col="col-md-3" />
                        <x-form-field name="price" label="Harga (Rp)" type="number" :value="$book->price !== null ? (int) $book->price : null"
                                      min="0" step="1" col="col-md-3" help="Opsional." />

                        <x-form-field name="description" label="Deskripsi" type="textarea" :value="$book->description" rows="5" maxlength="5000" />
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-image me-2"></i>Cover</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-image name="cover" label="Gambar Cover" :current="$book->cover ? $book->cover_url : null"
                                      :removable="(bool) $book->cover" />
                    </div>
                </div>
            </div>

            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-collection me-2"></i>Stok & Status</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="total_stock" :label="$book->exists ? 'Total Stok' : 'Jumlah Stok Awal'" type="number"
                                      :value="$book->exists ? $book->total_stock : 1" :required="true" min="0" max="100000"
                                      :help="$book->exists
                                          ? 'Sedang dipinjam: ' . $book->borrowed_stock . ' · Tersedia: ' . $book->available_stock . '. Total tidak boleh kurang dari jumlah yang sedang dipinjam.'
                                          : 'Seluruh stok awal langsung tersedia untuk dipinjam.'" />

                        @if ($book->exists)
                            <x-form-field name="stock_note" label="Alasan Perubahan Stok" maxlength="255"
                                          placeholder="Contoh: Tambahan dari hibah" help="Opsional. Dicatat di riwayat stok." />
                        @endif

                        <x-form-select name="status" label="Status" :required="true"
                                       :options="['active' => 'Aktif (tampil di katalog)', 'inactive' => 'Nonaktif (disembunyikan)']"
                                       :value="$book->status" placeholder="-- Pilih status --" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a href="{{ $book->exists ? Panel::route('books.show', $book) : Panel::route('books.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection
