<?php

namespace App\Http\Controllers\Anggota;

use App\Enums\FineStatus;
use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FineController extends Controller
{
    public function index(Request $request): View
    {
        $memberId = $request->user()->id; // selalu milik pengguna yang login

        $fines = Fine::query()
            ->where('member_id', $memberId)
            ->with([
                'payments' => fn ($q) => $q->oldest('paid_at')->oldest('id'),
                'returnItem.returnTransaction.borrowing:id,code',
                'returnItem.borrowingItem.book' => fn ($q) => $q->withTrashed()->select('id', 'title'),
            ])
            ->latest()->latest('id')
            ->paginate(10);

        return view('anggota.fines.index', [
            'fines' => $fines,
            'unpaidTotal' => (float) Fine::query()
                ->where('member_id', $memberId)
                ->where('status', FineStatus::Unpaid->value)
                ->sum(DB::raw('amount - paid_amount')),
        ]);
    }
}
