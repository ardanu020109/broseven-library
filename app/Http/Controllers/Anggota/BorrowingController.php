<?php

namespace App\Http\Controllers\Anggota;

use App\Enums\BorrowingStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBorrowingRequest;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function __construct(private readonly BorrowingService $borrowings) {}

    /** Peminjaman Saya: pengajuan yang menunggu dan buku yang sedang dipinjam. */
    public function index(Request $request): View
    {
        $borrowings = Borrowing::query()
            ->forMember($request->user()->id)
            ->whereIn('status', BorrowingStatus::openValues())
            ->with(['items.book' => fn($q) => $q->withTrashed()->select('id', 'title')])
            ->latest('requested_at')->latest('id')
            ->paginate(10);

        return view('anggota.borrowings.index', ['borrowings' => $borrowings]);
    }

    /** Riwayat: peminjaman yang sudah selesai, ditolak, atau dibatalkan. */
    public function history(Request $request): View
    {
        $borrowings = Borrowing::query()
            ->forMember($request->user()->id)
            ->whereIn('status', [
                BorrowingStatus::Returned->value,
                BorrowingStatus::Rejected->value,
                BorrowingStatus::Cancelled->value,
            ])
            ->with(['items.book' => fn($q) => $q->withTrashed()->select('id', 'title')])
            ->latest('requested_at')->latest('id')
            ->paginate(10);

        return view('anggota.borrowings.history', ['borrowings' => $borrowings]);
    }

    public function show(Borrowing $borrowing): View
    {
        Gate::authorize('view', $borrowing);

        $borrowing->load([
            'items.book' => fn($q) => $q->withTrashed(),
            'processor:id,name',
            'returns' => fn($q) => $q->oldest('return_date')->oldest('id'),
            'returns.items.borrowingItem.book' => fn($q) => $q->withTrashed()->select('id', 'title'),
        ]);

        return view('anggota.borrowings.show', ['borrowing' => $borrowing]);
    }

    public function store(StoreBorrowingRequest $request): RedirectResponse
    {
        try {
            $borrowing = $this->borrowings->request($request->user(), $request->validated('books'), $request->validated('notes'));
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('anggota.borrowings.show', $borrowing)
            ->with('success', 'Pengajuan peminjaman terkirim. Tunggu persetujuan petugas perpustakaan.');
    }

    public function cancel(Request $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('cancel', $borrowing);

        try {
            $this->borrowings->cancel($borrowing, $request->user());
        } catch (BusinessRuleException $e) {
            return redirect()->route('anggota.borrowings.show', $borrowing)->with('error', $e->getMessage());
        }

        return redirect()->route('anggota.borrowings.history')->with('success', 'Pengajuan berhasil dibatalkan.');
    }
}
