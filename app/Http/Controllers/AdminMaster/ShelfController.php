<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Requests\Master\ShelfRequest;
use App\Models\Shelf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShelfController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Shelf::class;
    }

    protected function resource(): string
    {
        return 'shelves';
    }

    protected function labels(): array
    {
        return ['singular' => 'Rak', 'plural' => 'Rak Buku', 'icon' => 'bi-bookshelf'];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name', 'location', 'description'];
    }

    protected function orderColumn(): string
    {
        return 'code';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Kode', 'value' => fn (Shelf $m) => $m->code],
            ['label' => 'Nama', 'value' => fn (Shelf $m) => $m->name],
            ['label' => 'Lokasi', 'value' => fn (Shelf $m) => $m->location ?: '-'],
            ['label' => 'Jumlah Buku', 'value' => fn (Shelf $m) => $m->books_count],
            ['label' => 'Keterangan', 'value' => fn (Shelf $m) => Str::limit((string) $m->description, 40) ?: '-'],
        ];
    }

    protected function details(Model $model): array
    {
        /** @var Shelf $model */
        return [
            'Kode Rak' => $model->code,
            'Nama Rak' => $model->name,
            'Lokasi' => $model->location ?: '-',
            'Keterangan' => $model->description ?: '-',
            'Jumlah Buku' => (string) $model->books_count,
        ];
    }

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function create(): View
    {
        return $this->form(new Shelf());
    }

    public function store(ShelfRequest $request): RedirectResponse
    {
        return $this->saveNew($request, $request->validated());
    }

    public function show(Shelf $shelf): View
    {
        return $this->detail($shelf);
    }

    public function edit(Shelf $shelf): View
    {
        return $this->form($shelf);
    }

    public function update(ShelfRequest $request, Shelf $shelf): RedirectResponse
    {
        return $this->saveExisting($request, $shelf, $request->validated());
    }

    public function destroy(Shelf $shelf): RedirectResponse
    {
        return $this->remove($shelf);
    }
}
