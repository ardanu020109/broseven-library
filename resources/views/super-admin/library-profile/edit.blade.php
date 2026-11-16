@extends('layouts.super-admin')

@section('title', 'Profil Perpustakaan')
@section('page_title', 'Profil Perpustakaan')

@section('content')
<x-page-header title="Profil Perpustakaan"
               subtitle="Data ini tampil di beranda, footer, Tentang Kami, Kontak, serta dokumen cetak." />

<form method="POST" action="{{ route('super-admin.library-profile.update') }}" enctype="multipart/form-data" data-once>
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-bank me-2"></i>Identitas</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="name" label="Nama Perpustakaan" :value="$profile->name" :required="true" maxlength="150" />
                        <x-form-field name="tagline" label="Tagline" :value="$profile->tagline" maxlength="200" />
                        <x-form-field name="description" label="Deskripsi" type="textarea" :value="$profile->description" rows="5" maxlength="5000" />
                        <x-form-field name="head_name" label="Nama Kepala Perpustakaan" :value="$profile->head_name" maxlength="150" />
                    </div>
                </div>
            </div>

            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-telephone me-2"></i>Kontak dan Operasional</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="address" label="Alamat" type="textarea" :value="$profile->address" maxlength="500" />
                        <x-form-field name="phone" label="Nomor Telepon" type="tel" :value="$profile->phone" maxlength="30" col="col-md-6" />
                        <x-form-field name="email" label="Email" type="email" :value="$profile->email" maxlength="255" col="col-md-6" />
                        <x-form-field name="website" label="Website" type="url" :value="$profile->website" maxlength="255"
                                      placeholder="https://contoh.id" help="Harus diawali http:// atau https://." />
                        <x-form-field name="operating_hours" label="Jam Operasional" type="textarea" :value="$profile->operating_hours" rows="4" maxlength="1000"
                                      help="Satu baris per hari atau kelompok hari." />
                    </div>
                </div>
            </div>

            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-share me-2"></i>Media Sosial</div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($networks as $key => $label)
                            <div class="col-md-6">
                                <label for="social_{{ $key }}" class="form-label fw-semibold">{{ $label }}</label>
                                <input type="url" id="social_{{ $key }}" name="social[{{ $key }}]" maxlength="255"
                                       value="{{ old("social.{$key}", $profile->social_media[$key] ?? '') }}"
                                       class="form-control @error("social.{$key}") is-invalid @enderror" placeholder="https://">
                                @error("social.{$key}")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header"><i class="bi bi-image me-2"></i>Logo</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-image name="logo" label="Logo Perpustakaan" :current="$profile->logo ? $profile->logo_url : null"
                                      :removable="(bool) $profile->logo" />
                    </div>
                </div>
            </div>

            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-journal-check me-2"></i>Aturan Peminjaman</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="max_books_per_loan" label="Maksimal Buku per Peminjaman" type="number"
                                      :value="$profile->max_books_per_loan" :required="true" min="1" max="20" col="col-md-6" />
                        <x-form-field name="loan_duration_days" label="Durasi Peminjaman (hari)" type="number"
                                      :value="$profile->loan_duration_days" :required="true" min="1" max="90" col="col-md-6" />
                        <x-form-field name="fine_per_day" label="Denda per Hari (Rp)" type="number"
                                      :value="$profile->fine_per_day !== null ? (int) $profile->fine_per_day : null"
                                      :required="true" min="0" max="1000000" step="1"
                                      help="Hanya berlaku untuk pengembalian berikutnya. Denda yang sudah tercatat tidak berubah." />
                        <x-form-field name="borrowing_rules" label="Teks Aturan Peminjaman" type="textarea" :value="$profile->borrowing_rules" rows="5" maxlength="5000"
                                      help="Ditampilkan di halaman Tentang Kami." />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
    </div>
</form>
@endsection
