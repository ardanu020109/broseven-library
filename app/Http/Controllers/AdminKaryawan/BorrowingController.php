<?php

namespace App\Http\Controllers\AdminKaryawan;

use App\Enums\BorrowingStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\LibraryProfile;
use App\Services\BorrowingService;
use App\Support\Panel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Dipakai bersama oleh Admin Karyawan dan Super Admin (route dan layout mengikuti role). */
class BorrowingController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(private readonly BorrowingService $borrowings)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-transactions');

        $term = $this->term($request);
        $status = $request->string('status')->toString();
        $status = in_array($status, array_column(BorrowingStatus::cases(), 'value'), true) ? $status : '';
        $from = $this->dateParam($request->string('from')->toString());
        $to = $this->dateParam($request->string('to')->toString());

        $borrowings = Borrowing::query()
            ->with(['member:id,name', 'items.book' => fn ($q) => $q->withTrashed()->select('id', 'title')])
            ->withSum('items as total_items', 'quantity')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('code', 'like', "%{$term}%")
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"))
                    ->orWhereHas('items.book', fn ($b) => $b->where('title', 'like', "%{$term}%"));
            }))
            // "Terlambat" dihitung dari tanggal jatuh tempo, bukan hanya dari status tersimpan.
            ->when($status === 'overdue', fn ($q) => $q
                ->whereIn('status', BorrowingStatus::stockHoldingValues())
                ->where('due_date', '<', today()->toDateString()))
            ->when($status !== '' && $status !== 'overdue', fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('requested_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('requested_at', '<=', $to->copy()->endOfDay()))
            ->latest('requested_at')->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin-karyawan.borrowings.index', [
            'borrowings' => $borrowings,
            'statuses' => collect(BorrowingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'filters' => ['q' => $term, 'status' => $status, 'from' => $from?->toDateString() ?? '', 'to' => $to?->toDateString() ?? ''],
            'hasFilters' => $term !== '' || $status !== '' || $from !== null || $to !== null,
        ]);
    }

    /** Daftar buku yang sedang dipinjam (satu baris per judul yang belum kembali). */
    public function active(Request $request): View
    {
        Gate::authorize('manage-transactions');

        $term = $this->term($request);
        $status = $request->string('status')->toString();
        $status = in_array($status, ['borrowed', 'partially_returned', 'overdue'], true) ? $status : '';
        $from = $this->dateParam($request->string('from')->toString());
        $to = $this->dateParam($request->string('to')->toString());

        $items = BorrowingItem::query()
            ->with(['borrowing.member:id,name', 'book' => fn ($q) => $q->withTrashed()->select('id', 'title')])
            ->whereColumn('returned_quantity', '<', 'quantity')
            ->whereHas('borrowing', function ($b) use ($term, $status, $from, $to) {
                $b->whereIn('status', BorrowingStatus::stockHoldingValues())
                    ->when($status === 'overdue', fn ($q) => $q->where('due_date', '<', today()->toDateString()))
                    ->when(in_array($status, ['borrowed', 'partially_returned'], true), fn ($q) => $q->where('status', $status))
                    ->when($from, fn ($q) => $q->where('borrow_date', '>=', $from->toDateString()))
                    ->when($to, fn ($q) => $q->where('borrow_date', '<=', $to->toDateString()))
                    ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                        $w->where('code', 'like', "%{$term}%")
                            ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"));
                    }));
            })
            ->when($term !== '', fn ($q) => $q->orWhere(function ($w) use ($term) {
                // Pencarian judul buku (digabung dengan kondisi buku belum kembali di bawah).
                $w->whereColumn('returned_quantity', '<', 'quantity')
                    ->whereHas('book', fn ($b) => $b->withTrashed()->where('title', 'like', "%{$term}%"))
                    ->whereHas('borrowing', fn ($b) => $b->whereIn('status', BorrowingStatus::stockHoldingValues()));
            }))
            ->orderBy(
                Borrowing::query()->select('due_date')->whereColumn('borrowings.id', 'borrowing_items.borrowing_id')
            )
            ->paginate(15)
            ->withQueryString();

        return view('admin-karyawan.borrowings.active', [
            'items' => $items,
            'filters' => ['q' => $term, 'status' => $status, 'from' => $from?->toDateString() ?? '', 'to' => $to?->toDateString() ?? ''],
            'hasFilters' => $term !== '' || $status !== '' || $from !== null || $to !== null,
        ]);
    }

    public function show(Borrowing $borrowing): View
    {
        Gate::authorize('view', $borrowing);
        Gate::authorize('manage-transactions');

        $borrowing->load([
            'member.memberProfile',
            'processor:id,name',
            'items.book' => fn ($q) => $q->withTrashed(),
            'returns' => fn ($q) => $q->oldest('return_date')->oldest('id'),
            'returns.items.borrowingItem.book' => fn ($q) => $q->withTrashed()->select('id', 'title'),
        ]);

        $profile = LibraryProfile::current();
        $isPending = $borrowing->status === BorrowingStatus::Pending;

        return view('admin-karyawan.borrowings.show', [
            'borrowing' => $borrowing,
            'problems' => $isPending ? $this->borrowings->approvalProblems($borrowing) : [],
            'dueDatePreview' => today()->addDays(max(1, (int) $profile->loan_duration_days)),
            'durationDays' => (int) $profile->loan_duration_days,
        ]);
    }

    public function approve(Request $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('approve', $borrowing);

        try {
            $this->borrowings->approve($borrowing, $request->user());
        } catch (BusinessRuleException $e) {
            return redirect(Panel::route('borrowings.show', $borrowing))->with('error', $e->getMessage());
        }

        return redirect(Panel::route('borrowings.show', $borrowing))
            ->with('success', 'Peminjaman disetujui. Stok buku sudah dikurangi dan tanggal jatuh tempo ditetapkan.');
    }

    public function reject(Request $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('reject', $borrowing);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Alasan penolakan wajib diisi.'],
            ['reason' => 'alasan penolakan'],
        );

        try {
            $this->borrowings->reject($borrowing, $request->user(), $data['reason']);
        } catch (BusinessRuleException $e) {
            return redirect(Panel::route('borrowings.show', $borrowing))->with('error', $e->getMessage());
        }

        return redirect(Panel::route('borrowings.show', $borrowing))->with('success', 'Pengajuan ditolak.');
    }

    /** Bukti peminjaman dalam PDF (hanya untuk peminjaman yang sudah disetujui). */
    public function receipt(Request $request, Borrowing $borrowing): Response|RedirectResponse
    {
        Gate::authorize('manage-transactions');

        if (in_array($borrowing->status, [BorrowingStatus::Pending, BorrowingStatus::Rejected, BorrowingStatus::Cancelled], true)) {
            return redirect(Panel::route('borrowings.show', $borrowing))
                ->with('error', 'Bukti peminjaman hanya tersedia untuk peminjaman yang sudah disetujui.');
        }

        $borrowing->load(['member.memberProfile', 'processor:id,name', 'items.book' => fn ($q) => $q->withTrashed()]);

        $pdf = Pdf::loadView('pdf.borrowing-receipt', [
            'borrowing' => $borrowing,
            'profile' => LibraryProfile::current(),
            'printedBy' => $request->user()->name,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("bukti-peminjaman-{$borrowing->code}.pdf");
    }

    private function term(Request $request): string
    {
        return trim(Str::limit($request->string('q')->toString(), 100, ''));
    }

    private function dateParam(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
