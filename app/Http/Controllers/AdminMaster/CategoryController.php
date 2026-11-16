<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Requests\Master\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function resource(): string
    {
        return 'categories';
    }

    protected function labels(): array
    {
        return ['singular' => 'Kategori', 'plural' => 'Kategori', 'icon' => 'bi-tags'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'slug', 'description'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Nama', 'value' => fn (Category $m) => $m->name],
            ['label' => 'Slug', 'value' => fn (Category $m) => $m->slug],
            ['label' => 'Jumlah Buku', 'value' => fn (Category $m) => $m->books_count],
            ['label' => 'Deskripsi', 'value' => fn (Category $m) => Str::limit((string) $m->description, 60) ?: '-'],
        ];
    }

    protected function details(Model $model): array
    {
        /** @var Category $model */
        return [
            'Nama' => $model->name,
            'Slug' => $model->slug,
            'Deskripsi' => $model->description ?: '-',
            'Jumlah Buku' => (string) $model->books_count,
        ];
    }

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function create(): View
    {
        return $this->form(new Category());
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        return $this->saveNew($request, $request->validated());
    }

    public function show(Category $category): View
    {
        return $this->detail($category);
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        return $this->saveExisting($request, $category, $request->validated());
    }

    public function destroy(Category $category): RedirectResponse
    {
        return $this->remove($category);
    }
}
