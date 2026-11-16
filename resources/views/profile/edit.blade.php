@extends(\App\Support\Panel::layout())

@section('title', 'Profil Saya')
@section('page_title', 'Profil Saya')

@section('content')
<x-page-header title="Profil Saya" subtitle="Kelola data diri dan keamanan akun Anda." />

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Data Diri</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-once>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <x-form-field name="name" label="Nama Lengkap" :value="$user->name" :required="true" maxlength="100" />
                        <x-form-field name="phone" label="Nomor Telepon" type="tel" :value="$user->phone" maxlength="20" col="col-md-6" />
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <input type="text" class="form-control" value="{{ $user->role->label() }}" disabled>
                        </div>
                        <x-form-field name="address" label="Alamat" type="textarea" :value="$user->address" maxlength="500" />
                        <x-form-image name="photo" label="Foto Profil" :current="$user->photo ? $user->photo_url : null" :removable="(bool) $user->photo" />
                    </div>

                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-check-lg me-1"></i>Simpan Profil</button>
                </form>
            </div>
        </div>

        <div class="card dash-card">
            <div class="card-header dash-card-header"><i class="bi bi-key me-2"></i>Ganti Password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}" data-once>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <x-form-field name="current_password" label="Password Saat Ini" type="password" :required="true" col="col-12" />
                        <x-form-field name="password" label="Password Baru" type="password" :required="true" col="col-md-6"
                                      help="Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka." />
                        <x-form-field name="password_confirmation" label="Konfirmasi Password Baru" type="password" :required="true" col="col-md-6" />
                    </div>
                    <p class="small text-muted mt-3 mb-3">Sesi login Anda di perangkat lain akan diakhiri.</p>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Ubah Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header"><i class="bi bi-person-badge me-2"></i>Akun</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-sienna">Username</dt><dd class="col-sm-8">{{ $user->username }}</dd>
                    <dt class="col-sm-4 text-sienna">Email</dt><dd class="col-sm-8 text-break">{{ $user->email }}</dd>
                    <dt class="col-sm-4 text-sienna">Login terakhir</dt>
                    <dd class="col-sm-8">{{ $user->last_login_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd>
                </dl>
                <p class="small text-muted mt-3 mb-0">Untuk mengubah username, email, atau role, hubungi Super Admin.</p>
            </div>
        </div>

        @if ($user->memberProfile)
            <div class="card dash-card">
                <div class="card-header dash-card-header"><i class="bi bi-person-vcard me-2"></i>Keanggotaan</div>
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-5 text-sienna">Nomor anggota</dt><dd class="col-sm-7">{{ $user->memberProfile->member_number }}</dd>
                        <dt class="col-sm-5 text-sienna">Bergabung</dt><dd class="col-sm-7">{{ $user->memberProfile->joined_at->translatedFormat('d F Y') }}</dd>
                        <dt class="col-sm-5 text-sienna">Berlaku s.d.</dt><dd class="col-sm-7">{{ $user->memberProfile->expired_at?->translatedFormat('d F Y') ?? '-' }}</dd>
                        <dt class="col-sm-5 text-sienna">Status</dt>
                        <dd class="col-sm-7">
                            <span class="badge badge-status {{ $user->memberProfile->is_valid ? 'badge-available' : 'badge-overdue' }}">
                                {{ $user->memberProfile->is_valid ? 'Berlaku' : 'Tidak berlaku' }}
                            </span>
                        </dd>
                    </dl>
                    @if ($user->role === \App\Enums\Role::Anggota)
                        <a href="{{ route('anggota.membership-card.show') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person-vcard me-1"></i>Lihat Kartu Anggota
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
