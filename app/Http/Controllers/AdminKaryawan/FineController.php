<?php

namespace App\Http\Controllers\AdminKaryawan;

use App\Enums\FineStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Services\FineService;
use App\Support\Panel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Dipakai bersama oleh Admin Karyawan dan Super Admin. Pembebasan hanya untuk Super Admin. */
class FineController extends Controller
{
    public const METHODS = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'other' => 'Lainnya'];

    public function __construct(private readonly FineService $fines)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-transactions');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $status = $request->string('status')->toString();
        $status = in_array($status, array_column(FineStatus::cases(), 'value'), true) ? $status : '';
        $from = $this->dateParam($request->string('from')->toString());
        $to = $this->dateParam($request->string('to')->toString());

        $fines = Fine::query()
            ->with([
                'member:id,name',
                'returnItem.returnTransaction.borrowing:id,code',
                'returnItem.borrowingItem.book' => fn ($q) => $q->withTrashed()->select('id', 'title'),
            ])
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->whereHas('member', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"))
                    ->orWhereHas('returnItem.returnTransaction.borrowing', fn ($b) => $b->where('code', 'like', "%{$term}%"))
                    ->orWhereHas('returnItem.returnTransaction', fn ($r) => $r->where('code', 'like', "%{$term}%"));
            }))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest()->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin-karyawan.fines.index', [
            'fines' => $fines,
            'statuses' => collect(FineStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'summary' => [
                'unpaid_total' => (float) Fine::query()->where('status', FineStatus::Unpaid->value)->sum(DB::raw('amount - paid_amount')),
                'unpaid_count' => Fine::query()->where('status', FineStatus::Unpaid->value)->count(),
                'collected' => (float) FinePayment::query()->where('type', FinePayment::TYPE_PAYMENT)->sum('amount'),
            ],
            'filters' => ['q' => $term, 'status' => $status, 'from' => $from?->toDateString() ?? '', 'to' => $to?->toDateString() ?? ''],
            'hasFilters' => $term !== '' || $status !== '' || $from !== null || $to !== null,
        ]);
    }

    public function show(Fine $fine): View
    {
        Gate::authorize('manage-transactions');

        $fine->load([
            'member.memberProfile',
            'settler:id,name',
            'payments' => fn ($q) => $q->oldest('paid_at')->oldest('id'),
            'payments.receiver:id,name',
            'returnItem.returnTransaction.borrowing:id,code',
            'returnItem.returnTransaction.processor:id,name',
            'returnItem.borrowingItem.book' => fn ($q) => $q->withTrashed(),
        ]);

        return view('admin-karyawan.fines.show', [
            'fine' => $fine,
            'methods' => self::METHODS,
            'canWaive' => Gate::allows('waive-fines'),
        ]);
    }

    public function pay(Request $request, Fine $fine): RedirectResponse
    {
        Gate::authorize('manage-transactions');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'method' => ['required', Rule::in(array_keys(self::METHODS))],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'nominal pembayaran', 'method' => 'metode pembayaran', 'notes' => 'catatan']);

        try {
            $this->fines->pay($fine, $request->user(), $data['amount'], $data['method'], $data['notes'] ?? null);
        } catch (BusinessRuleException $e) {
            return redirect(Panel::route('fines.show', $fine))->withInput()->with('error', $e->getMessage());
        }

        return redirect(Panel::route('fines.show', $fine))->with('success', 'Pembayaran denda berhasil dicatat.');
    }

    public function waive(Request $request, Fine $fine): RedirectResponse
    {
        Gate::authorize('waive-fines');

        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:255']],
            ['reason.required' => 'Alasan pembebasan wajib diisi.'],
            ['reason' => 'alasan pembebasan'],
        );

        try {
            $this->fines->waive($fine, $request->user(), $data['reason']);
        } catch (BusinessRuleException $e) {
            return redirect(Panel::route('fines.show', $fine))->with('error', $e->getMessage());
        }

        return redirect(Panel::route('fines.show', $fine))->with('success', 'Sisa denda berhasil dibebaskan.');
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
