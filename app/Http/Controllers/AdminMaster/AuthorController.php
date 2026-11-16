<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Requests\Master\AuthorRequest;
use App\Models\Author;
use App\Services\ImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthorController extends MasterDataController
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    protected function modelClass(): string
    {
        return Author::class;
    }

    protected function resource(): string
    {
        return 'authors';
    }

    protected function labels(): array
    {
        return ['singular' => 'Penulis', 'plural' => 'Penulis', 'icon' => 'bi-person-lines-fill'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'biography'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Foto', 'type' => 'image', 'value' => fn (Author $m) => $m->photo_url],
            ['label' => 'Nama', 'value' => fn (Author $m) => $m->name],
            ['label' => 'Jumlah Buku', 'value' => fn (Author $m) => $m->books_count],
            ['label' => 'Biografi', 'value' => fn (Author $m) => Str::limit((string) $m->biography, 60) ?: '-'],
        ];
    }

    protected function details(Model $model): array
    {
        /** @var Author $model */
        return [
            'Nama' => $model->name,
            'Biografi' => $model->biography ?: '-',
            'Jumlah Buku' => (string) $model->books_count,
        ];
    }

    protected function imageUrl(Model $model): ?string
    {
        /** @var Author $model */
        return $model->photo_url;
    }

    /** Proses upload, penggantian, dan penghapusan foto sebelum data disimpan. */
    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        /** @var Author|null $model */
        unset($data['photo'], $data['remove_photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->images->store($request->file('photo'), 'authors');
            $this->images->delete($model?->photo);
        } elseif ($model && $request->boolean('remove_photo')) {
            $this->images->delete($model->photo);
            $data['photo'] = null;
        }

        return $data;
    }

    protected function cleanup(Model $model): void
    {
        /** @var Author $model */
        $this->images->delete($model->photo);
    }

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function create(): View
    {
        return $this->form(new Author());
    }

    public function store(AuthorRequest $request): RedirectResponse
    {
        return $this->saveNew($request, $request->validated());
    }

    public function show(Author $author): View
    {
        return $this->detail($author);
    }

    public function edit(Author $author): View
    {
        return $this->form($author);
    }

    public function update(AuthorRequest $request, Author $author): RedirectResponse
    {
        return $this->saveExisting($request, $author, $request->validated());
    }

    public function destroy(Author $author): RedirectResponse
    {
        return $this->remove($author);
    }
}
