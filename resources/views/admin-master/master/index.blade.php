@extends(\App\Support\Panel::layout())

@use('App\Support\Panel')

@section('title', 'Data ' . $labels['plural'])
@section('page_title', $labels['plural'])

@section('content')
<x-page-header :title="'Data ' . $labels['plural']" :subtitle="$items->total() . ' data terdaftar'">
    <a href="{{ Panel::route($resource . '.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Tambah {{ $labels['singular'] }}
    </a>
</x-page-header>

<div class="card dash-card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ Panel::route($resource . '.index') }}" class="row g-2" role="search">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ $term }}" maxlength="100" class="form-control"
                           placeholder="Cari {{ \Illuminate\Support\Str::lower($labels['plural']) }}..." aria-label="Cari">
                    <button type="submit" class="btn btn-primary">Cari</button>
                </div>
            </div>
            @if ($term !== '')
                <div class="col-auto">
                    <a href="{{ Panel::route($resource . '.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 3.5rem">No</th>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="text-end" style="width: 9rem">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $items->firstItem() + $loop->index }}</td>
                        @foreach ($columns as $column)
                            <td>
                                @if (($column['type'] ?? 'text') === 'image')
                                    <img src="{{ $column['value']($item) }}" alt="" class="thumb rounded-circle" style="width:38px;height:38px">
                                @else
                                    {{ $column['value']($item) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <a href="{{ Panel::route($resource . '.show', $item) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ Panel::route($resource . '.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Ubah" aria-label="Ubah">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" aria-label="Hapus"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-delete-url="{{ Panel::route($resource . '.destroy', $item) }}"
                                    data-delete-name="{{ $item->name }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 2 }}" class="empty-note">
                            {{ $term !== '' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $items->links() }}
        </div>
    @endif
</div>

<x-delete-modal />
@endsection
