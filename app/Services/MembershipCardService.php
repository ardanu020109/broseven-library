<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\LibraryProfile;
use App\Models\MemberProfile;
use App\Models\User;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

class MembershipCardService
{
    /** Ukuran kartu CR80 (85,6 x 53,98 mm) dalam point. */
    private const CARD_WIDTH_PT = 242.65;
    private const CARD_HEIGHT_PT = 153.01;

    public function __construct(private readonly QrCodeService $qr)
    {
    }

    /**
     * Status keanggotaan untuk ditampilkan di kartu dan halaman verifikasi.
     *
     * @return array{label: string, valid: bool}
     */
    public function status(User $owner, MemberProfile $profile): array
    {
        $label = match (true) {
            ! $owner->is_active => 'Akun nonaktif',
            $profile->status === MemberProfile::STATUS_SUSPENDED => 'Ditangguhkan',
            $profile->status === MemberProfile::STATUS_EXPIRED,
            $profile->expired_at !== null && $profile->expired_at->lt(today()) => 'Kedaluwarsa',
            default => 'Aktif',
        };

        return ['label' => $label, 'valid' => $label === 'Aktif'];
    }

    /** Alamat yang tertanam di QR Code. Hanya memuat token acak, tanpa data pribadi. */
    public function verifyUrl(MemberProfile $profile): string
    {
        return route('membership.verify', ['token' => $profile->card_token]);
    }

    /**
     * Data untuk tampilan layar.
     *
     * @return array{status: array{label: string, valid: bool}, qr: string, verifyUrl: string}
     */
    public function viewData(User $owner, MemberProfile $profile): array
    {
        $url = $this->verifyUrl($profile);

        return [
            'status' => $this->status($owner, $profile),
            'qr' => $this->qr->svgDataUri($url, 256),
            'verifyUrl' => $url,
        ];
    }

    /** Kartu dalam PDF: halaman 1 sisi depan, halaman 2 sisi belakang. */
    public function pdf(User $owner, MemberProfile $profile): DomPdf
    {
        $library = LibraryProfile::current();

        return Pdf::loadView('pdf.membership-card', [
            'user' => $owner,
            'profile' => $profile,
            'status' => $this->status($owner, $profile),
            'library' => $library,
            'logo' => PdfAssets::logoDataUri($library->logo),
            'photo' => PdfAssets::croppedImageDataUri($owner->photo, 138, 174),
            'qr' => $this->qr->pngDataUri($this->verifyUrl($profile), 300),
        ])->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT], 'portrait');
    }

    /** Membuat token baru. Token lama langsung tidak berlaku (QR pada kartu lama tidak bisa diverifikasi). */
    public function regenerateToken(MemberProfile $profile, User $actor): MemberProfile
    {
        $profile->update(['card_token' => MemberProfile::generateCardToken()]);

        ActivityLog::record(
            'member.card_regenerated',
            "Memperbarui kode QR kartu anggota {$profile->member_number}",
            $profile,
            [],
            $actor->id,
        );

        return $profile;
    }
}
