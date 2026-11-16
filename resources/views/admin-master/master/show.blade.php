@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Detail ' . $labels['singular'])
@section('page_title', $labels['plural'])

@section('content')
<x-page-header :title="'Detail ' . $labels['singular']" :subtitle="$model->name">
    <div class="d-flex gap-2">
        <a href="{{ Panel::route($resource . '.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <a href="{{ Panel::route($resource . '.edit', $model) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i>Ubah
        </a>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                data-delete-url="{{ Panel::route($resource . '.destroy', $model) }}"
                data-delete-name="{{ $model->name }}">
            <i class="bi bi-trash me-1"></i>Hapus
        </button>
    </div>
</x-page-header>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi {{ $labels['icon'] }} me-2"></i>Informasi {{ $labels['singular'] }}</div>
            <div class="card-body">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="" class="upload-preview mb-3">
                @endif

                <dl class="row mb-0">
                    @foreach ($details as $label => $value)
                        <dt class="col-sm-4 text-sienna">{{ $label }}</dt>
                        <dd class="col-sm-8">{!! nl2br(e($value)) !!}</dd>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card dash-card h-100">
            <div class="card-header dash-card-header"><i class="bi bi-journal-bookmark me-2"></i>Buku Terkait</div>
            <div class="card-body p-0">
                @forelse ($books as $book)
                    <div class="list-row">
                        <div class="flex-grow-1">
                            <a href="{{ route('catalog.show', $book) }}" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">{{ $book->title }}</a>
                            <div class="small text-muted">{{ $book->isbn ?: 'Tanpa ISBN' }}</div>
                        </div>
                        <span class="badge badge-status badge-borrowed">{{ $book->available_stock }}/{{ $book->total_stock }}</span>
                    </div>
                @empty
                    <p class="empty-note">Belum ada buku yang memakai data ini.</p>
                @endforelse

                @if ($model->books_count > $books->count())
                    <p class="small text-muted text-center py-2 mb-0">dan {{ $model->books_count - $books->count() }} buku lainnya</p>
                @endif
            </div>
        </div>
    </div>
</div>

<x-delete-modal />
@endsection
