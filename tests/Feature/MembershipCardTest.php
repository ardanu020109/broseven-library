<?php

namespace Tests\Feature;

use App\Models\MemberProfile;
use App\Models\User;
use App\Services\MembershipCardService;
use App\Services\QrCodeService;
use App\Support\PdfAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class MembershipCardTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['name' => 'Perpustakaan Uji', 'address' => 'Jl. Uji No. 1', 'phone' => '021 555 0100', 'email' => 'info@uji.test']);
    }

    /** @return array<string, array{string}> */
    public static function staffRoles(): array
    {
        return ['admin karyawan' => ['admin-karyawan'], 'super admin' => ['super-admin']];
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master']];
    }

    private function assertIsPdf($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ------------------------------------------------------------------ kartu anggota

    public function test_member_sees_own_card_front_and_back(): void
    {
        $member = $this->makeMember(['name' => 'Budi Pemilik', 'username' => 'budi.pemilik']);
        $profile = $member->memberProfile;

        $this->actingAs($member)->get('/anggota/membership-card')
            ->assertOk()
            ->assertSee('Budi Pemilik')
            ->assertSee($profile->member_number)
            ->assertSee('budi.pemilik')
            ->assertSee('Perpustakaan Uji')
            ->assertSee('Tampilan Depan')
            ->assertSee('Tampilan Belakang')
            ->assertSee('Aktif')
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('Unduh PDF')
            ->assertSee('Cetak');
    }

    public function test_card_shows_why_an_invalid_membership_cannot_borrow(): void
    {
        $expired = $this->makeMember([], ['expired_at' => today()->subDay()->toDateString()]);

        $this->actingAs($expired)->get('/anggota/membership-card')
            ->assertOk()->assertSee('Kedaluwarsa')->assertSee('belum dapat dipakai untuk meminjam');

        $suspended = $this->makeMember([], ['status' => MemberProfile::STATUS_SUSPENDED]);
        $this->actingAs($suspended)->get('/anggota/membership-card')->assertSee('Ditangguhkan');
    }

    public function test_user_without_membership_profile_gets_a_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/anggota/membership-card')->assertOk()->assertSee('belum memiliki data keanggotaan');
        $this->actingAs($user)->get('/anggota/membership-card/pdf')
            ->assertRedirect(route('anggota.membership-card.show'))->assertSessionHas('error');
        $this->actingAs($user)->post('/anggota/membership-card/regenerate')->assertSessionHas('error');
    }

    public function test_member_cannot_see_another_members_card_by_manipulating_the_url(): void
    {
        $mine = $this->makeMember(['name' => 'Anggota Saya']);
        $theirs = $this->makeMember(['name' => 'Anggota Lain']);

        $this->actingAs($mine)->get("/anggota/membership-card?user={$theirs->id}&id={$theirs->id}&member={$theirs->id}")
            ->assertSee('Anggota Saya')
            ->assertDontSee('Anggota Lain')
            ->assertDontSee($theirs->memberProfile->member_number);

        $this->actingAs($mine)->get("/anggota/membership-card/pdf?user={$theirs->id}&download=1")
            ->assertHeader('content-disposition', 'attachment; filename="kartu-anggota-' . $mine->memberProfile->member_number . '.pdf"');
    }

    public function test_member_can_open_staff_card_routes_neither(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();

        $this->actingAs($member)->get("/admin-karyawan/members/{$other->id}/card")->assertForbidden();
        $this->actingAs($member)->get("/super-admin/members/{$other->id}/card/pdf")->assertForbidden();
    }

    // ------------------------------------------------------------------ PDF

    public function test_card_pdf_is_a_two_page_pdf_named_after_the_member_number(): void
    {
        $member = $this->makeMember();
        $number = $member->memberProfile->member_number;

        $inline = $this->actingAs($member)->get('/anggota/membership-card/pdf');
        $this->assertIsPdf($inline);
        $this->assertStringContainsString('inline', (string) $inline->headers->get('content-disposition'));
        $this->assertStringContainsString($number, (string) $inline->headers->get('content-disposition'));

        // Halaman 1 = depan, halaman 2 = belakang.
        $this->assertSame(2, preg_match_all('#/Type\s*/Page(?!s)#', $inline->getContent()));

        $download = $this->actingAs($member)->get('/anggota/membership-card/pdf?download=1');
        $this->assertIsPdf($download);
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }

    public function test_card_pdf_renders_with_photo_and_logo_present_or_missing(): void
    {
        Storage::fake('public');
        $member = $this->makeMember();

        Storage::disk('public')->put('avatars/foto.jpg', UploadedFile::fake()->image('foto.jpg', 400, 200)->getContent());
        Storage::disk('public')->put('logos/logo.png', UploadedFile::fake()->image('logo.png', 120, 120)->getContent());
        $member->update(['photo' => 'avatars/foto.jpg']);
        \App\Models\LibraryProfile::firstOrFail()->update(['logo' => 'logos/logo.png']);
        $this->assertIsPdf($this->actingAs($member)->get('/anggota/membership-card/pdf'));

        // File hilang: kartu tetap bisa dibuat (memakai inisial).
        $member->update(['photo' => 'avatars/hilang.jpg']);
        \App\Models\LibraryProfile::firstOrFail()->update(['logo' => 'logos/hilang.png']);
        $this->assertIsPdf($this->actingAs($member)->get('/anggota/membership-card/pdf'));
    }

    // ------------------------------------------------------------------ akses petugas

    #[DataProvider('staffRoles')]
    public function test_authorized_staff_can_view_and_print_any_member_card(string $role): void
    {
        $staff = $this->makeStaff($role);
        $member = $this->makeMember(['name' => 'Anggota Dicetak']);

        $this->actingAs($staff)->get("/{$role}/members/{$member->id}/card")
            ->assertOk()->assertSee('Anggota Dicetak')->assertSee($member->memberProfile->member_number)->assertSee('Cetak');

        $this->assertIsPdf($this->actingAs($staff)->get("/{$role}/members/{$member->id}/card/pdf"));
    }

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_open_member_cards(string $role): void
    {
        $user = $this->makeStaff($role);
        $member = $this->makeMember();

        $this->actingAs($user)->get("/admin-karyawan/members/{$member->id}/card")->assertForbidden();
        $this->actingAs($user)->get("/super-admin/members/{$member->id}/card/pdf")->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $member = $this->makeMember();

        $this->get('/anggota/membership-card')->assertRedirect(route('login'));
        $this->get('/anggota/membership-card/pdf')->assertRedirect(route('login'));
        $this->get("/admin-karyawan/members/{$member->id}/card")->assertRedirect(route('login'));
    }

    public function test_staff_card_routes_reject_non_members_and_members_without_profile(): void
    {
        $staff = $this->makeStaff();
        $anotherStaff = $this->makeStaff('admin-master');
        $noProfile = User::factory()->create();

        $this->actingAs($staff)->get("/admin-karyawan/members/{$anotherStaff->id}/card")->assertNotFound();
        $this->actingAs($staff)->get("/admin-karyawan/members/{$anotherStaff->id}/card/pdf")->assertNotFound();

        $this->actingAs($staff)->get("/admin-karyawan/members/{$noProfile->id}/card")
            ->assertRedirect("/admin-karyawan/members/{$noProfile->id}")->assertSessionHas('error');
    }

    public function test_card_buttons_appear_on_member_detail_pages(): void
    {
        $member = $this->makeMember();

        $this->actingAs($this->makeStaff())->get("/admin-karyawan/members/{$member->id}")
            ->assertSee(route('admin-karyawan.members.card', $member), false);

        $this->actingAs($this->makeStaff('super-admin'))->get("/super-admin/users/{$member->id}")
            ->assertSee(route('super-admin.members.card', $member), false);

        $this->actingAs($member)->get('/anggota/dashboard')
            ->assertSee(route('anggota.membership-card.show'), false);
    }

    // ------------------------------------------------------------------ token dan verifikasi publik

    public function test_qr_url_contains_only_the_random_token(): void
    {
        $member = $this->makeMember(['name' => 'Rahasia Pribadi', 'email' => 'rahasia@pribadi.test']);
        $profile = $member->memberProfile;
        $url = app(MembershipCardService::class)->verifyUrl($profile);

        $this->assertSame(64, strlen($profile->card_token));
        $this->assertSame(route('membership.verify', ['token' => $profile->card_token]), $url);
        $this->assertStringNotContainsString($member->email, $url);
        $this->assertStringNotContainsString(rawurlencode($member->name), $url);
        $this->assertStringNotContainsString((string) $profile->member_number, $url);
    }

    public function test_public_verification_shows_minimal_data_and_is_not_cacheable(): void
    {
        $member = $this->makeMember(
            ['name' => 'Siti Terverifikasi', 'email' => 'siti@rahasia.test', 'phone' => '081299990000', 'address' => 'Jl. Pribadi 99'],
        );
        $profile = $member->memberProfile;

        $response = $this->get(route('membership.verify', ['token' => $profile->card_token]))
            ->assertOk()
            ->assertSee('Kartu Anggota Berlaku')
            ->assertSee('Siti Terverifikasi')
            ->assertSee($profile->member_number)
            ->assertSee('Berlaku')
            ->assertDontSee('siti@rahasia.test')
            ->assertDontSee('081299990000')
            ->assertDontSee('Jl. Pribadi 99');

        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_verification_reports_expired_suspended_and_inactive_cards_as_invalid(): void
    {
        $expired = $this->makeMember(['name' => 'Anggota Kedaluwarsa'], ['expired_at' => today()->subDay()->toDateString()]);
        $suspended = $this->makeMember(['name' => 'Anggota Ditangguhkan'], ['status' => MemberProfile::STATUS_SUSPENDED]);
        $inactive = $this->makeMember(['name' => 'Anggota Nonaktif', 'is_active' => false]);

        $this->get(route('membership.verify', ['token' => $expired->memberProfile->card_token]))
            ->assertOk()->assertSee('Kartu Tidak Berlaku')->assertSee('Kedaluwarsa');
        $this->get(route('membership.verify', ['token' => $suspended->memberProfile->card_token]))
            ->assertOk()->assertSee('Kartu Tidak Berlaku')->assertSee('Ditangguhkan');
        $this->get(route('membership.verify', ['token' => $inactive->memberProfile->card_token]))
            ->assertOk()->assertSee('Kartu Tidak Berlaku')->assertSee('Akun nonaktif');
    }

    public function test_unknown_malformed_and_wrong_case_tokens_are_not_found(): void
    {
        $member = $this->makeMember();
        $token = $member->memberProfile->card_token;

        $this->get(route('membership.verify', ['token' => str_repeat('a', 64)]))->assertNotFound()->assertSee('Kartu Tidak Dikenali');
        $this->get('/verifikasi-kartu/terlalu-pendek')->assertNotFound();
        $this->get('/verifikasi-kartu/' . str_repeat('!', 64))->assertNotFound();

        // Token harus cocok persis, termasuk huruf besar-kecil.
        $swapped = strtoupper($token) === $token ? strtolower($token) : strtoupper($token);
        $this->get(route('membership.verify', ['token' => $swapped]))->assertNotFound();
    }

    public function test_verification_of_a_deleted_account_is_not_found(): void
    {
        $member = $this->makeMember();
        $token = $member->memberProfile->card_token;
        $member->delete();

        $this->get(route('membership.verify', ['token' => $token]))->assertNotFound();
    }

    public function test_verification_is_rate_limited(): void
    {
        $url = route('membership.verify', ['token' => str_repeat('b', 64)]);

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertNotFound();
        }

        $this->get($url)->assertStatus(429);
    }

    public function test_regenerating_the_token_invalidates_the_old_qr(): void
    {
        $member = $this->makeMember();
        $old = $member->memberProfile->card_token;
        $oldQr = app(MembershipCardService::class)->viewData($member, $member->memberProfile)['qr'];

        $this->actingAs($member)->post('/anggota/membership-card/regenerate')
            ->assertRedirect(route('anggota.membership-card.show'))
            ->assertSessionHas('success');

        $fresh = $member->fresh()->memberProfile;
        $this->assertNotSame($old, $fresh->card_token);
        $this->assertSame(64, strlen($fresh->card_token));
        $this->assertNotSame($oldQr, app(MembershipCardService::class)->viewData($member->fresh(), $fresh)['qr']);

        $this->get(route('membership.verify', ['token' => $old]))->assertNotFound();
        $this->get(route('membership.verify', ['token' => $fresh->card_token]))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $member->id, 'action' => 'member.card_regenerated']);
    }

    public function test_only_members_can_regenerate_their_token(): void
    {
        $member = $this->makeMember();
        $token = $member->memberProfile->card_token;

        $this->actingAs($this->makeStaff())->post('/anggota/membership-card/regenerate')->assertForbidden();
        $this->post('/anggota/membership-card/regenerate')->assertRedirect(route('login'));

        $this->assertSame($token, $member->fresh()->memberProfile->card_token);
    }

    // ------------------------------------------------------------------ QR dan gambar

    public function test_qr_service_produces_svg_and_png(): void
    {
        $qr = app(QrCodeService::class);

        $svg = $qr->svg('https://contoh.test/verifikasi-kartu/abc');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->svgDataUri('x'));

        $png = $qr->pngDataUri('https://contoh.test/verifikasi-kartu/abc');
        $this->assertStringStartsWith('data:image/png;base64,', $png);
        $this->assertSame("\x89PNG", substr(base64_decode(substr($png, strlen('data:image/png;base64,'))), 0, 4));

        // Isi sama menghasilkan QR yang sama, isi berbeda menghasilkan QR berbeda.
        $this->assertSame($qr->svg('sama'), $qr->svg('sama'));
        $this->assertNotSame($qr->svg('satu'), $qr->svg('dua'));
    }

    public function test_cropped_image_has_the_requested_size_regardless_of_source_ratio(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('avatars/lebar.png', UploadedFile::fake()->image('lebar.png', 600, 200)->getContent());
        Storage::disk('public')->put('avatars/tinggi.jpg', UploadedFile::fake()->image('tinggi.jpg', 200, 700)->getContent());

        foreach (['avatars/lebar.png', 'avatars/tinggi.jpg'] as $path) {
            $uri = PdfAssets::croppedImageDataUri($path, 138, 174);
            $this->assertStringStartsWith('data:image/jpeg;base64,', $uri);

            [$width, $height] = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/jpeg;base64,'))));
            $this->assertSame([138, 174], [$width, $height]);
        }
    }

    public function test_cropped_image_returns_null_for_missing_or_invalid_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/rusak.jpg', 'bukan gambar');

        $this->assertNull(PdfAssets::croppedImageDataUri(null, 10, 10));
        $this->assertNull(PdfAssets::croppedImageDataUri('', 10, 10));
        $this->assertNull(PdfAssets::croppedImageDataUri('avatars/tidak-ada.jpg', 10, 10));
        $this->assertNull(PdfAssets::croppedImageDataUri('avatars/rusak.jpg', 10, 10));
    }

    public function test_member_menu_card_item_is_enabled(): void
    {
        $this->actingAs($this->makeMember())->get('/anggota/dashboard')
            ->assertSee(route('anggota.membership-card.show'), false);
    }
}
