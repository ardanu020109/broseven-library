<?php

namespace App\Http\Controllers\AdminMaster;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

abstract class MasterDataController extends Controller
{
    private const PER_PAGE = 10;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Segmen route dan folder view, contoh: "categories". */
    abstract protected function resource(): string;

    /** @return array{singular: string, plural: string, icon: string} */
    abstract protected function labels(): array;

    /** @return array<int, string> Kolom yang dicari lewat kotak pencarian. */
    abstract protected function searchColumns(): array;

    /** @return array<int, array{label: string, value: \Closure, type?: string}> */
    abstract protected function columns(): array;

    /** @return array<string, string> Label => nilai untuk halaman detail. */
    abstract protected function details(Model $model): array;

    protected function orderColumn(): string
    {
        return 'name';
    }

    /** Kesempatan untuk memproses data (misalnya upload file) sebelum disimpan. */
    protected function prepareData(Request $request, array $data, ?Model $model = null): array
    {
        return $data;
    }

    /** Dipanggil setelah data berhasil dihapus (misalnya menghapus file). */
    protected function cleanup(Model $model): void
    {
        //
    }

    protected function imageUrl(Model $model): ?string
    {
        return null;
    }

    /**
     * Alasan data tidak boleh dihapus. Buku yang sudah di-soft-delete tetap dihitung
     * karena foreign key database masih mengikat barisnya.
     */
    protected function blockedReason(Model $model): ?string
    {
        $count = $model->books()->withTrashed()->count();

        if ($count === 0) {
            return null;
        }

        return "{$this->labels()['singular']} \"{$model->name}\" tidak dapat dihapus karena masih dipakai oleh {$count} buku.";
    }

    // ------------------------------------------------------------------

    protected function listing(Request $request): View
    {
        Gate::authorize('manage-master-data');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $class = $this->modelClass();

        $items = $class::query()
            ->withCount('books')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($where) use ($term) {
                    foreach ($this->searchColumns() as $column) {
                        $where->orWhere($column, 'like', "%{$term}%");
                    }
                });
            })
            ->orderBy($this->orderColumn())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin-master.master.index', [
            'resource' => $this->resource(),
            'labels' => $this->labels(),
            'columns' => $this->columns(),
            'items' => $items,
            'term' => $term,
        ]);
    }

    protected function form(Model $model): View
    {
        Gate::authorize('manage-master-data');

        return view('admin-master.master.form', [
            'resource' => $this->resource(),
            'labels' => $this->labels(),
            'model' => $model,
        ]);
    }

    protected function detail(Model $model): View
    {
        Gate::authorize('manage-master-data');

        $model->loadCount('books');

        return view('admin-master.master.show', [
            'resource' => $this->resource(),
            'labels' => $this->labels(),
            'model' => $model,
            'details' => $this->details($model),
            'imageUrl' => $this->imageUrl($model),
            'books' => $model->books()->latest()->take(10)->get(['id', 'title', 'isbn', 'total_stock', 'available_stock']),
        ]);
    }

    protected function saveNew(Request $request, array $data): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        $class = $this->modelClass();
        $model = DB::transaction(fn () => $class::create($this->prepareData($request, $data)));

        ActivityLog::record(
            'master.created',
            'Menambah ' . Str::lower($this->labels()['singular']) . ': ' . $model->name,
            $model,
        );

        return redirect(Panel::route($this->resource() . '.index'))
            ->with('success', $this->labels()['singular'] . ' berhasil ditambahkan.');
    }

    protected function saveExisting(Request $request, Model $model, array $data): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        DB::transaction(fn () => $model->update($this->prepareData($request, $data, $model)));

        ActivityLog::record(
            'master.updated',
            'Mengubah ' . Str::lower($this->labels()['singular']) . ': ' . $model->name,
            $model,
        );

        return redirect(Panel::route($this->resource() . '.index'))
            ->with('success', $this->labels()['singular'] . ' berhasil diperbarui.');
    }

    protected function remove(Model $model): RedirectResponse
    {
        Gate::authorize('manage-master-data');

        $index = Panel::route($this->resource() . '.index');

        if ($reason = $this->blockedReason($model)) {
            return redirect($index)->with('error', $reason);
        }

        $name = $model->name;
        $id = $model->getKey();

        try {
            DB::transaction(fn () => $model->delete());
        } catch (QueryException) {
            return redirect($index)->with('error', 'Data tidak dapat dihapus karena masih digunakan oleh data lain.');
        }

        $this->cleanup($model);

        ActivityLog::record(
            'master.deleted',
            'Menghapus ' . Str::lower($this->labels()['singular']) . ': ' . $name,
            null,
            ['id' => $id, 'name' => $name],
        );

        return redirect($index)->with('success', $this->labels()['singular'] . ' berhasil dihapus.');
    }
}
