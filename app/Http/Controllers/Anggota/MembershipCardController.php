<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Services\MembershipCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Sengaja TANPA parameter ID: anggota hanya bisa membuka kartunya sendiri,
 * apa pun yang dikirim lewat URL (aman dari IDOR).
 */
class MembershipCardController extends Controller
{
    public function __construct(private readonly MembershipCardService $cards)
    {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        Gate::authorize('view-member-card', $user);

        $profile = $user->memberProfile;

        return view('anggota.membership-card.show', [
            'user' => $user,
            'profile' => $profile,
            'card' => $profile ? $this->cards->viewData($user, $profile) : null,
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view-member-card', $user);

        $profile = $user->memberProfile;

        if (! $profile) {
            return redirect()->route('anggota.membership-card.show')
                ->with('error', 'Anda belum memiliki data keanggotaan.');
        }

        $pdf = $this->cards->pdf($user, $profile);
        $filename = "kartu-anggota-{$profile->member_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view-member-card', $user);

        $profile = $user->memberProfile;

        if (! $profile) {
            return redirect()->route('anggota.membership-card.show')
                ->with('error', 'Anda belum memiliki data keanggotaan.');
        }

        $this->cards->regenerateToken($profile, $user);

        return redirect()->route('anggota.membership-card.show')
            ->with('success', 'Kode QR berhasil diperbarui. Kartu lama tidak berlaku lagi, silakan unduh atau cetak kartu yang baru.');
    }
}
