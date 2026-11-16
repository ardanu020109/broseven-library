<?php

namespace Tests\Feature;

use App\Models\Fine;
use App\Models\FinePayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class FineManagementTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeLibraryProfile();
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'anggota' => ['anggota']];
    }

    // ------------------------------------------------------------------ daftar

    public function test_fine_list_summary_and_filters(): void
    {
        $staff = $this->makeStaff();
        $a = $this->makeMember(['name' => 'Anggota Tunggakan']);
        $b = $this->makeMember(['name' => 'Anggota Lunas']);

        $this->makeFine($a, $staff, 4000);
        $this->makeFine($b, $staff, 6000, ['status' => 'paid', 'paid_amount' => 6000]);

        $this->actingAs($staff)->get('/admin-karyawan/fines')
            ->assertOk()
            ->assertSee('Anggota Tunggakan')->assertSee('Anggota Lunas')
            ->assertViewHas('summary', fn (array $s) => $s['unpaid_total'] === 4000.0 && $s['unpaid_count'] === 1);

        $this->actingAs($staff)->get('/admin-karyawan/fines?status=paid')->assertSee('Anggota Lunas')->assertDontSee('Anggota Tunggakan');
        $this->actingAs($staff)->get('/admin-karyawan/fines?q=Tunggakan')->assertSee('Anggota Tunggakan')->assertDontSee('Anggota Lunas');
        $this->actingAs($staff)->get('/admin-karyawan/fines?status=ngawur&from=bukan&to=2026-99-99')->assertOk();
    }

    public function test_fine_detail_shows_the_calculation(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->get("/admin-karyawan/fines/{$fine->id}")
            ->assertOk()->assertSee('2 hari')->assertSee('Rp4.000')->assertSee('Catat Pembayaran');
    }

    // ------------------------------------------------------------------ pembayaran

    public function test_full_payment_settles_the_fine_and_writes_history(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 4000, 'method' => 'cash', 'notes' => 'Lunas di loket'])
            ->assertRedirect("/admin-karyawan/fines/{$fine->id}")->assertSessionHas('success');

        $fine->refresh();
        $this->assertSame('paid', $fine->status->value);
        $this->assertSame('4000.00', $fine->paid_amount);
        $this->assertNotNull($fine->settled_at);
        $this->assertSame($staff->id, $fine->settled_by);

        $this->assertDatabaseHas('fine_payments', [
            'fine_id' => $fine->id, 'type' => 'payment', 'amount' => 4000, 'method' => 'cash',
            'received_by' => $staff->id, 'notes' => 'Lunas di loket',
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'fine.paid']);
    }

    public function test_installments_keep_the_fine_open_until_fully_paid(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 1500, 'method' => 'transfer']);
        $fine->refresh();
        $this->assertSame('unpaid', $fine->status->value);
        $this->assertSame('1500.00', $fine->paid_amount);
        $this->assertSame(2500.0, $fine->remaining_amount);
        $this->assertNull($fine->settled_at);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 2500, 'method' => 'cash']);
        $this->assertSame('paid', $fine->fresh()->status->value);
        $this->assertSame(2, $fine->payments()->count());
    }

    public function test_invalid_payments_are_refused(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);
        $url = "/admin-karyawan/fines/{$fine->id}/payments";

        $this->actingAs($staff)->post($url, ['amount' => 5000, 'method' => 'cash'])->assertSessionHas('error'); // melebihi sisa
        $this->actingAs($staff)->post($url, ['amount' => 0, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($staff)->post($url, ['amount' => -10, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($staff)->post($url, ['amount' => 1000, 'method' => 'kartu-ajaib'])->assertSessionHasErrors('method');
        $this->actingAs($staff)->post($url, ['method' => 'cash'])->assertSessionHasErrors('amount');

        $this->assertSame('0.00', $fine->fresh()->paid_amount);
        $this->assertSame(0, $fine->payments()->count());
    }

    public function test_settled_fines_cannot_be_paid_again(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 4000, 'method' => 'cash']);
        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertSessionHas('error');

        $this->assertSame('4000.00', $fine->fresh()->paid_amount);
        $this->assertSame(1, $fine->payments()->count());
    }

    // ------------------------------------------------------------------ pembebasan

    public function test_super_admin_can_waive_the_remaining_fine_with_a_reason(): void
    {
        $admin = $this->makeStaff('super-admin');
        $fine = $this->makeFine($this->makeMember(), $this->makeStaff(), 4000);

        $this->actingAs($this->makeStaff())->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 1500, 'method' => 'cash']);

        $this->actingAs($admin)->post("/super-admin/fines/{$fine->id}/waive", [])->assertSessionHasErrors('reason');
        $this->assertSame('unpaid', $fine->fresh()->status->value);

        $this->actingAs($admin)->post("/super-admin/fines/{$fine->id}/waive", ['reason' => 'Kendala banjir'])->assertSessionHas('success');

        $fine->refresh();
        $this->assertSame('waived', $fine->status->value);
        $this->assertSame($admin->id, $fine->settled_by);
        $this->assertSame('1500.00', $fine->paid_amount); // pembayaran sebelumnya tetap tercatat
        $this->assertDatabaseHas('fine_payments', ['fine_id' => $fine->id, 'type' => 'waiver', 'amount' => 2500, 'notes' => 'Kendala banjir']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'fine.waived']);

        // Setelah dibebaskan tidak bisa dibayar lagi.
        $this->actingAs($admin)->post("/super-admin/fines/{$fine->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertSessionHas('error');
    }

    public function test_admin_karyawan_cannot_waive_fines(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/waive", ['reason' => 'Kasihan'])->assertForbidden();

        $this->assertSame('unpaid', $fine->fresh()->status->value);
        $this->assertSame(0, FinePayment::count());

        // Tombol pembebasan juga tidak ditampilkan.
        $this->actingAs($staff)->get("/admin-karyawan/fines/{$fine->id}")->assertDontSee('Bebaskan Sisa Denda');
        $this->actingAs($this->makeStaff('super-admin'))->get("/super-admin/fines/{$fine->id}")->assertSee('Bebaskan Sisa Denda');
    }

    public function test_paid_fines_cannot_be_waived(): void
    {
        $admin = $this->makeStaff('super-admin');
        $fine = $this->makeFine($this->makeMember(), $admin, 4000);

        $this->actingAs($admin)->post("/super-admin/fines/{$fine->id}/payments", ['amount' => 4000, 'method' => 'cash']);
        $this->actingAs($admin)->post("/super-admin/fines/{$fine->id}/waive", ['reason' => 'Terlambat'])->assertSessionHas('error');

        $this->assertSame('paid', $fine->fresh()->status->value);
    }

    // ------------------------------------------------------------------ hak akses

    #[DataProvider('outsiders')]
    public function test_other_roles_cannot_access_staff_fine_pages(string $role): void
    {
        $user = $this->makeStaff($role);
        $fine = $this->makeFine($this->makeMember(), $this->makeStaff(), 4000);

        $this->actingAs($user)->get('/admin-karyawan/fines')->assertForbidden();
        $this->actingAs($user)->get("/super-admin/fines/{$fine->id}")->assertForbidden();
        $this->actingAs($user)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertForbidden();

        $this->assertSame('0.00', $fine->fresh()->paid_amount);
    }

    public function test_member_sees_only_own_fines(): void
    {
        $staff = $this->makeStaff();
        $mine = $this->makeMember(['name' => 'Anggota Saya']);
        $theirs = $this->makeMember(['name' => 'Anggota Lain']);

        $myFine = $this->makeFine($mine, $staff, 4000);
        $this->makeFine($theirs, $staff, 9000);
        $this->actingAs($staff)->post("/admin-karyawan/fines/{$myFine->id}/payments", ['amount' => 1000, 'method' => 'cash']);

        $this->actingAs($mine)->get('/anggota/fines')
            ->assertOk()
            ->assertSee('Rp3.000') // sisa denda milik sendiri
            ->assertDontSee('9.000')
            ->assertViewHas('unpaidTotal', 3000.0)
            ->assertViewHas('fines', fn ($fines) => $fines->total() === 1);
    }

    public function test_member_without_fines_sees_friendly_message(): void
    {
        $this->actingAs($this->makeMember())->get('/anggota/fines')->assertOk()->assertSee('Anda tidak memiliki denda');
    }

    public function test_staff_cannot_open_member_fine_page(): void
    {
        $this->actingAs($this->makeStaff())->get('/anggota/fines')->assertForbidden();
    }

    public function test_dashboard_unpaid_total_follows_partial_payments(): void
    {
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff, 4000);

        $this->actingAs($staff)->post("/admin-karyawan/fines/{$fine->id}/payments", ['amount' => 1500, 'method' => 'cash']);

        $this->actingAs($staff)->get('/admin-karyawan/dashboard')
            ->assertViewHas('stats', fn (array $s) => $s['fines_unpaid'] === 2500.0);
    }

    // ------------------------------------------------------------------ integritas

    public function test_a_return_item_can_have_only_one_fine(): void
    {
        $fine = $this->makeFine($this->makeMember(), $this->makeStaff(), 4000);

        $this->expectException(UniqueConstraintViolationException::class);

        Fine::create([
            'return_item_id' => $fine->return_item_id,
            'member_id' => $fine->member_id,
            'amount' => 1000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
    }
}
