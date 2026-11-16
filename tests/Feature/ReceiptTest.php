<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReturnTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile(['address' => 'Jl. Uji No. 1', 'phone' => '021 123 456']);
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'anggota' => ['anggota']];
    }

    private function assertIsPdf($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function returnedBorrowing(): array
    {
        $staff = $this->makeStaff();
        $borrowing = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create(['title' => 'Buku Struk'])], [
            'due_date' => today()->subDays(2)->toDateString(),
        ]);
        $item = $borrowing->items()->firstOrFail();
        $this->actingAs($staff)->post("/admin-karyawan/returns/create/{$borrowing->id}", ['items' => [$item->id => 1]]);

        return [$staff, $borrowing, ReturnTransaction::firstOrFail()];
    }

    public function test_return_receipt_is_a_pdf(): void
    {
        [$staff, , $return] = $this->returnedBorrowing();

        $response = $this->actingAs($staff)->get("/admin-karyawan/returns/{$return->id}/receipt");

        $this->assertIsPdf($response);
        $this->assertStringContainsString($return->code, (string) $response->headers->get('content-disposition'));
    }

    public function test_borrowing_receipt_is_a_pdf_for_approved_loans(): void
    {
        $staff = $this->makeStaff();
        $running = $this->makeBorrowed($this->makeMember(), $staff, [Book::factory()->withStock(3, 2)->create()]);

        $this->assertIsPdf($this->actingAs($staff)->get("/admin-karyawan/borrowings/{$running->id}/receipt"));

        [, $finished] = $this->returnedBorrowing();
        $this->assertIsPdf($this->actingAs($staff)->get("/admin-karyawan/borrowings/{$finished->id}/receipt"));
    }

    public function test_borrowing_receipt_is_refused_for_unapproved_requests(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $book = Book::factory()->create();

        foreach (['pending', 'rejected', 'cancelled'] as $status) {
            $borrowing = $this->makePending($member, [$book], ['status' => $status]);

            $this->actingAs($staff)->get("/admin-karyawan/borrowings/{$borrowing->id}/receipt")
                ->assertRedirect("/admin-karyawan/borrowings/{$borrowing->id}")
                ->assertSessionHas('error');
        }
    }

    public function test_super_admin_can_print_receipts_too(): void
    {
        [, $borrowing, $return] = $this->returnedBorrowing();
        $admin = $this->makeStaff('super-admin');

        $this->assertIsPdf($this->actingAs($admin)->get("/super-admin/returns/{$return->id}/receipt"));
        $this->assertIsPdf($this->actingAs($admin)->get("/super-admin/borrowings/{$borrowing->id}/receipt"));
    }

    public function test_receipt_still_renders_with_a_logo_and_with_a_missing_logo_file(): void
    {
        Storage::fake('public');
        [$staff, , $return] = $this->returnedBorrowing();

        $profile = \App\Models\LibraryProfile::firstOrFail();

        Storage::disk('public')->put('logos/logo.png', UploadedFile::fake()->image('logo.png', 120, 120)->getContent());
        $profile->update(['logo' => 'logos/logo.png']);
        $this->assertIsPdf($this->actingAs($staff)->get("/admin-karyawan/returns/{$return->id}/receipt"));

        $profile->update(['logo' => 'logos/hilang.png']); // file tidak ada
        $this->assertIsPdf($this->actingAs($staff)->get("/admin-karyawan/returns/{$return->id}/receipt"));
    }

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_print_receipts(string $role): void
    {
        [, $borrowing, $return] = $this->returnedBorrowing();
        $user = $this->makeStaff($role);

        $this->actingAs($user)->get("/admin-karyawan/returns/{$return->id}/receipt")->assertForbidden();
        $this->actingAs($user)->get("/admin-karyawan/borrowings/{$borrowing->id}/receipt")->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $borrowing, $return] = $this->returnedBorrowing();
        auth()->logout();

        $this->get("/admin-karyawan/returns/{$return->id}/receipt")->assertRedirect(route('login'));
        $this->get("/admin-karyawan/borrowings/{$borrowing->id}/receipt")->assertRedirect(route('login'));
    }
}
