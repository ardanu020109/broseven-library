@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', ($model->exists ? 'Ubah ' : 'Tambah ') . $labels['singular'])
@section('page_title', $labels['plural'])

@section('content')
<x-page-header :title="($model->exists ? 'Ubah ' : 'Tambah ') . $labels['singular']"
               subtitle="Kolom bertanda * wajib diisi.">
    <a href="{{ Panel::route($resource . '.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" data-once
              action="{{ $model->exists ? Panel::route($resource . '.update', $model) : Panel::route($resource . '.store') }}">
            @csrf
            @if ($model->exists)
                @method('PUT')
            @endif

            <div class="row g-3">
                @include('admin-master.' . $resource . '.fields', ['model' => $model])
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                </button>
                <a href="{{ Panel::route($resource . '.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
