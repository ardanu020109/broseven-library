<?php

namespace App\Http\Controllers\AdminKaryawan;

use App\Enums\BorrowingStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReturnRequest;
use App\Models\Borrowing;
use App\Models\LibraryProfile;
use App\Models\ReturnTransaction;
use App\Services\FineCalculator;
use App\Services\ReturnService;
use App\Support\Panel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Dipakai bersama oleh Admin Karyawan dan Super Admin (route dan layout mengikuti role). */
class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returns,
        private readonly FineCalculator $fines,
    ) {
    }

    /** Pencarian peminjaman yang masih berjalan, plus daftar pengembalian terakhir. */
    public function index(Request $request): View
    {
        Gate::authorize('manage-transactions');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));

        $borrowings = Borrowing::query()
            ->whereIn('status', BorrowingStatus::stockHoldingValues())
            ->whereHas('items', fn ($i) => $i->whereColumn('returned_quantity', '<', 'quantity'))
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('code', 'like', "%{$term}%")
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"))
                    ->orWhereHas('member.memberProfile', fn ($p) => $p->where('member_number', 'like', "%{$term}%"));
            }))
            ->with(['member.memberProfile', 'items.book' => fn ($q) => $q->withTrashed()->select('id', 'title')])
            ->orderBy('due_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin-karyawan.returns.index', [
            'borrowings' => $borrowings,
            'term' => $term,
            'recentReturns' => ReturnTransaction::query()
                ->with('borrowing.member:id,name')
                ->withSum('items as total_quantity', 'quantity')
                ->withSum('items as total_fine', 'fine_amount')
                ->latest()->latest('id')
                ->take(10)->get(),
        ]);
    }

    public function create(Borrowing $borrowing): View|RedirectResponse
    {
        Gate::authorize('manage-transactions');

        if (! $borrowing->status->holdsStock()) {
            return redirect(Panel::route('borrowings.show', $borrowing))
                ->with('error', 'Peminjaman ini tidak sedang berjalan sehingga tidak bisa diproses pengembaliannya.');
        }

        $borrowing->load(['member.memberProfile', 'items.book' => fn ($q) => $q->withTrashed()]);

        $perDay = (float) LibraryProfile::current()->fine_per_day;
        $dueDate = $borrowing->due_date ?? today();

        // Pratinjau denda per eksemplar jika dikembalikan hari ini.
        $rows = $borrowing->items
            ->filter(fn ($item) => $item->outstanding_quantity > 0)
            ->map(fn ($item) => [
                'item' => $item,
                'calc' => $this->fines->calculate($dueDate, today(), $perDay, 1),
            ])
            ->values();

        return view('admin-karyawan.returns.create', [
            'borrowing' => $borrowing,
            'rows' => $rows,
        ]);
    }

    public function store(StoreReturnRequest $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('manage-transactions');

        try {
            $return = $this->returns->process($borrowing, $request->validated('items'), $request->user(), $request->validated('notes'));
        } catch (BusinessRuleException $e) {
            return redirect(Panel::route('returns.create', $borrowing))->withInput()->with('error', $e->getMessage());
        }

        return redirect(Panel::route('returns.show', $return))
            ->with('success', 'Pengembalian berhasil dicatat. Stok buku sudah diperbarui.');
    }

    public function show(ReturnTransaction $returnTransaction): View
    {
        Gate::authorize('manage-transactions');

        $returnTransaction->load([
            'borrowing.member.memberProfile',
            'processor:id,name',
            'items.fine',
            'items.borrowingItem.book' => fn ($q) => $q->withTrashed(),
        ]);

        return view('admin-karyawan.returns.show', ['returnTransaction' => $returnTransaction]);
    }

    /** Bukti pengembalian dalam PDF. */
    public function receipt(Request $request, ReturnTransaction $returnTransaction): Response
    {
        Gate::authorize('manage-transactions');

        $returnTransaction->load([
            'borrowing.member.memberProfile',
            'processor:id,name',
            'items.fine',
            'items.borrowingItem.book' => fn ($q) => $q->withTrashed(),
        ]);

        $pdf = Pdf::loadView('pdf.return-receipt', [
            'returnTx' => $returnTransaction,
            'printedBy' => $request->user()->name,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("bukti-pengembalian-{$returnTransaction->code}.pdf");
    }
}
