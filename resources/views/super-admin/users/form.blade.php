@extends('layouts.super-admin')

@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('page_title', 'Pengguna')

@section('content')
<x-page-header :title="$user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna'" subtitle="Kolom bertanda * wajib diisi.">
    <a href="{{ $user->exists ? route('super-admin.users.show', $user) : route('super-admin.users.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</x-page-header>

<form method="POST" enctype="multipart/form-data" data-once
      action="{{ $user->exists ? route('super-admin.users.update', $user) : route('super-admin.users.store') }}">
    @csrf
    @if ($user->exists)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Data Pengguna</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-field name="name" label="Nama Lengkap" :value="$user->name" :required="true" maxlength="100" />
                        <x-form-field name="username" label="Username" :value="$user->username" :required="true" maxlength="30" col="col-md-6"
                                      help="Huruf, angka, strip, dan garis bawah." />
                        <x-form-field name="email" label="Email" type="email" :value="$user->email" :required="true" maxlength="255" col="col-md-6" />
                        <x-form-field name="phone" label="Nomor Telepon" type="tel" :value="$user->phone" maxlength="20" col="col-md-6" />
                        <x-form-select name="role" label="Role" :options="$roles" :value="$user->role?->value" :required="true" col="col-md-6"
                                       placeholder="-- Pilih role --"
                                       :help="$user->exists ? 'Role tidak dapat diubah untuk akun sendiri atau pengguna yang memiliki riwayat peminjaman.' : null" />
                        <x-form-field name="address" label="Alamat" type="textarea" :value="$user->address" maxlength="500" />

                        @unless ($user->exists)
                            <x-form-field name="password" label="Password" type="password" :required="true" col="col-md-6"
                                          help="Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka." />
                            <x-form-field name="password_confirmation" label="Konfirmasi Password" type="password" :required="true" col="col-md-6" />

                            <div class="col-12">
                                <input type="hidden" name="is_active" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                           @checked(old('is_active', 1))>
                                    <label class="form-check-label" for="is_active">Akun aktif (dapat login)</label>
                                </div>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-image me-2"></i>Foto Profil</div>
                <div class="card-body">
                    <div class="row g-3">
                        <x-form-image name="photo" label="Foto" :current="$user->exists && $user->photo ? $user->photo_url : null"
                                      :removable="$user->exists && (bool) $user->photo" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a href="{{ $user->exists ? route('super-admin.users.show', $user) : route('super-admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection
