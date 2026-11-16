@extends('layouts.super-admin')

@section('title', $user->name)
@section('page_title', 'Pengguna')

@section('content')
    @php
        $roleBadge = [
            'super-admin' => 'bg-dark-red',
            'admin-master' => 'bg-crimson',
            'admin-karyawan' => 'bg-sienna',
            'anggota' => 'bg-beige',
        ];
        $isSelf = auth()->id() === $user->id;
    @endphp

    <x-page-header title="Detail Pengguna" :subtitle="$user->name">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary"><i
                    class="bi bi-arrow-left me-1"></i>Kembali</a>
            <a href="{{ route('super-admin.users.edit', $user) }}" class="btn btn-primary"><i
                    class="bi bi-pencil me-1"></i>Ubah</a>
            @if ($user->memberProfile)
                <a href="{{ route('super-admin.members.card', $user) }}" class="btn btn-outline-primary"><i
                        class="bi bi-person-vcard me-1"></i>Kartu Anggota</a>
            @endif

            @unless ($isSelf)
                <form method="POST" action="{{ route('super-admin.users.toggle-status', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                        <i
                            class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }} me-1"></i>{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                    data-delete-url="{{ route('super-admin.users.destroy', $user) }}"
                    data-delete-name="{{ $user->name }} ({{ '@' . $user->username }})">
                    <i class="bi bi-trash me-1"></i>Hapus
                </button>
            @endunless
        </div>
    </x-page-header>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-person me-2"></i>Informasi Akun</div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3 text-center">
                            <img src="{{ $user->photo_url }}" alt="" class="cover-large rounded-circle"
                                style="aspect-ratio:1/1">
                            <div class="mt-2 d-flex flex-column gap-1 align-items-center">
                                <span class="badge {{ $roleBadge[$user->role->value] }}">{{ $user->role->label() }}</span>
                                <span
                                    class="badge badge-status {{ $user->is_active ? 'badge-available' : 'badge-overdue' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-sienna">Nama</dt>
                                <dd class="col-sm-8">{{ $user->name }}</dd>
                                <dt class="col-sm-4 text-sienna">Username</dt>
                                <dd class="col-sm-8">{{ $user->username }}</dd>
                                <dt class="col-sm-4 text-sienna">Email</dt>
                                <dd class="col-sm-8">{{ $user->email }}</dd>
                                <dt class="col-sm-4 text-sienna">Telepon</dt>
                                <dd class="col-sm-8">{{ $user->phone ?: '-' }}</dd>
                                <dt class="col-sm-4 text-sienna">Alamat</dt>
                                <dd class="col-sm-8">{!! $user->address ? nl2br(e($user->address)) : '-' !!}</dd>
                                <dt class="col-sm-4 text-sienna">Dibuat</dt>
                                <dd class="col-sm-8">{{ $user->created_at->translatedFormat('d F Y') }}</dd>
                                <dt class="col-sm-4 text-sienna">Login terakhir</dt>
                                <dd class="col-sm-8">
                                    {{ $user->last_login_at?->translatedFormat('d F Y H:i') ?? 'Belum pernah' }}</dd>
                                @if ($user->memberProfile)
                                    <dt class="col-sm-4 text-sienna">Nomor anggota</dt>
                                    <dd class="col-sm-8">{{ $user->memberProfile->member_number }}</dd>
                                    <dt class="col-sm-4 text-sienna">Masa berlaku</dt>
                                    <dd class="col-sm-8">
                                        {{ $user->memberProfile->joined_at->translatedFormat('d M Y') }}
                                        s.d. {{ $user->memberProfile->expired_at?->translatedFormat('d M Y') ?? '-' }}
                                        <span
                                            class="badge badge-status {{ $user->memberProfile->is_valid ? 'badge-available' : 'badge-overdue' }}">
                                            {{ $user->memberProfile->is_valid ? 'Berlaku' : 'Tidak berlaku' }}
                                        </span>
                                    </dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-bar-chart me-2"></i>Ringkasan</div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between py-1"><span>Total
                                peminjaman</span><strong>{{ $stats['borrowings'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Peminjaman belum
                                selesai</span><strong>{{ $stats['open'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Denda belum
                                dibayar</span><strong>{{ $stats['fines_unpaid'] }}</strong></li>
                        <li class="d-flex justify-content-between py-1"><span>Transaksi diproses
                                petugas</span><strong>{{ $stats['processed'] }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-key me-2"></i>Reset Password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" data-once>
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <x-form-field name="password" label="Password Baru" type="password" :required="true"
                                help="Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka." />
                            <x-form-field name="password_confirmation" label="Konfirmasi Password" type="password"
                                :required="true" />
                        </div>
                        <p class="small text-muted mt-3 mb-3">Sesi login lain milik pengguna ini akan diakhiri.</p>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Reset
                            Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header"><i class="bi bi-activity me-2"></i>Aktivitas Terakhir</div>
                <div class="card-body p-0">
                    @forelse ($activities as $log)
                        @php
                            [$label, $icon, $tone] = \App\Support\ActivityLabel::describe($log->action);
                        @endphp
                        <div class="list-row align-items-start">
                            <span class="activity-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                            <div class="flex-grow-1">
                                <strong>{{ $label }}</strong>
                                @if ($log->description)
                                    <div class="small text-muted">{{ $log->description }}</div>
                                @endif
                            </div>
                            <time class="small text-muted text-nowrap">{{ $log->created_at->diffForHumans() }}</time>
                        </div>
                    @empty
                        <p class="empty-note">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <x-delete-modal />
@endsection
