<?php

namespace App\Http\Controllers\AdminKaryawan;

use App\Enums\BorrowingStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Data anggota (hanya baca) untuk petugas layanan. */
class MemberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-transactions');

        $term = trim(Str::limit($request->string('q')->toString(), 100, ''));
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : '';

        $members = User::query()
            ->role(Role::Anggota)
            ->with('memberProfile')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('memberProfile', fn ($p) => $p->where('member_number', 'like', "%{$term}%"));
            }))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin-karyawan.members.index', [
            'members' => $members,
            'filters' => ['q' => $term, 'status' => $status],
            'hasFilters' => $term !== '' || $status !== '',
        ]);
    }

    public function show(User $member): View
    {
        Gate::authorize('manage-transactions');

        abort_unless($member->role === Role::Anggota, 404);

        $member->load('memberProfile');

        return view('admin-karyawan.members.show', [
            'member' => $member,
            'stats' => [
                'total' => Borrowing::query()->forMember($member->id)->count(),
                'open' => Borrowing::query()->forMember($member->id)->whereIn('status', BorrowingStatus::openValues())->count(),
                'overdue' => Borrowing::query()->forMember($member->id)
                    ->whereIn('status', BorrowingStatus::stockHoldingValues())
                    ->where('due_date', '<', today()->toDateString())->count(),
                'fines_unpaid' => (float) Fine::query()->where('member_id', $member->id)->where('status', 'unpaid')
                    ->sum(\Illuminate\Support\Facades\DB::raw('amount - paid_amount')),
            ],
            'borrowings' => Borrowing::query()
                ->forMember($member->id)
                ->with(['items.book' => fn ($q) => $q->withTrashed()->select('id', 'title')])
                ->latest('requested_at')->latest('id')
                ->take(10)->get(),
        ]);
    }
}
