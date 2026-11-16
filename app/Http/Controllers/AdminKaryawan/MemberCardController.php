<?php

namespace App\Http\Controllers\AdminKaryawan;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MembershipCardService;
use App\Support\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Kartu anggota untuk petugas (Admin Karyawan dan Super Admin). */
class MemberCardController extends Controller
{
    public function __construct(private readonly MembershipCardService $cards)
    {
    }

    public function show(User $member): View|RedirectResponse
    {
        abort_unless($member->role === Role::Anggota, 404);
        Gate::authorize('view-member-card', $member);

        $profile = $member->memberProfile;

        if (! $profile) {
            return redirect(Panel::route('members.show', $member))
                ->with('error', 'Pengguna ini belum memiliki data keanggotaan.');
        }

        return view('admin-karyawan.members.card', [
            'member' => $member,
            'profile' => $profile,
            'card' => $this->cards->viewData($member, $profile),
        ]);
    }

    public function pdf(Request $request, User $member): Response|RedirectResponse
    {
        abort_unless($member->role === Role::Anggota, 404);
        Gate::authorize('view-member-card', $member);

        $profile = $member->memberProfile;

        if (! $profile) {
            return redirect(Panel::route('members.show', $member))
                ->with('error', 'Pengguna ini belum memiliki data keanggotaan.');
        }

        $pdf = $this->cards->pdf($member, $profile);
        $filename = "kartu-anggota-{$profile->member_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
