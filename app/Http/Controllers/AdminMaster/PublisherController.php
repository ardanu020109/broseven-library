<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Requests\Master\PublisherRequest;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublisherController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Publisher::class;
    }

    protected function resource(): string
    {
        return 'publishers';
    }

    protected function labels(): array
    {
        return ['singular' => 'Penerbit', 'plural' => 'Penerbit', 'icon' => 'bi-building'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'address', 'phone', 'email'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Nama', 'value' => fn (Publisher $m) => $m->name],
            ['label' => 'Telepon', 'value' => fn (Publisher $m) => $m->phone ?: '-'],
            ['label' => 'Email', 'value' => fn (Publisher $m) => $m->email ?: '-'],
            ['label' => 'Jumlah Buku', 'value' => fn (Publisher $m) => $m->books_count],
            ['label' => 'Alamat', 'value' => fn (Publisher $m) => Str::limit((string) $m->address, 40) ?: '-'],
        ];
    }

    protected function details(Model $model): array
    {
        /** @var Publisher $model */
        return [
            'Nama' => $model->name,
            'Alamat' => $model->address ?: '-',
            'Telepon' => $model->phone ?: '-',
            'Email' => $model->email ?: '-',
            'Jumlah Buku' => (string) $model->books_count,
        ];
    }

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function create(): View
    {
        return $this->form(new Publisher());
    }

    public function store(PublisherRequest $request): RedirectResponse
    {
        return $this->saveNew($request, $request->validated());
    }

    public function show(Publisher $publisher): View
    {
        return $this->detail($publisher);
    }

    public function edit(Publisher $publisher): View
    {
        return $this->form($publisher);
    }

    public function update(PublisherRequest $request, Publisher $publisher): RedirectResponse
    {
        return $this->saveExisting($request, $publisher, $request->validated());
    }

    public function destroy(Publisher $publisher): RedirectResponse
    {
        return $this->remove($publisher);
    }
}
