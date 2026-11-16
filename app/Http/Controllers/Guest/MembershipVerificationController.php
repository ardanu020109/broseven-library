<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Services\MembershipCardService;
use Illuminate\Http\Response;

/** Halaman publik yang dibuka saat QR pada kartu dipindai. */
class MembershipVerificationController extends Controller
{
    public function __invoke(MembershipCardService $cards, string $token): Response
    {
        $profile = MemberProfile::query()->with('user')->where('card_token', $token)->first();

        // Database bisa tidak membedakan huruf besar-kecil; pastikan token cocok persis.
        $found = $profile && $profile->user && hash_equals($profile->card_token, $token);

        $response = response()->view('guest.membership.verify', [
            'profile' => $found ? $profile : null,
            'owner' => $found ? $profile->user : null,
            'status' => $found ? $cards->status($profile->user, $profile) : null,
        ], $found ? 200 : 404);

        return $response->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
