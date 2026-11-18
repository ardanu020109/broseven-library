<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Fine;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;
use App\Models\User;
use App\Support\Menu;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function borrowing(User $member, User $staff, Book $book, array $attributes = [], int $quantity = 1, int $returned = 0): Borrowing
    {
        $borrowing = Borrowing::factory()->create(array_merge(['member_id' => $member->id, 'processed_by' => $staff->id], $attributes));

        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id,
            'book_id' => $book->id,
            'quantity' => $quantity,
            'returned_quantity' => $returned,
        ]);

        return $borrowing;
    }

    public function test_super_admin_dashboard_shows_all_sections(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin/dashboard')
            ->assertOk()
            ->assertSee('Total Buku')
            ->assertSee('Total Eksemplar')
            ->assertSee('Stok Tersedia')
            ->assertSee('Total Anggota')
            ->assertSee('Total Admin')
            ->assertSee('Peminjaman Aktif')
            ->assertSee('Total Pengembalian')
            ->assertSee('Buku Terlambat')
            ->assertSee('Total Denda')
            ->assertSee('Peminjaman Bulanan')
            ->assertSee('Pengembalian Bulanan')
            ->assertSee('Buku Paling Populer')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('chart.umd.js');
    }

    public function test_super_admin_statistics_are_calculated_from_database(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = User::factory()->adminKaryawan()->create();
        $members = User::factory()->count(2)->create();

        $stocked = Book::factory()->withStock(10, 7)->create();
        $other = Book::factory()->withStock(5, 5)->create();

        // Sedang dipinjam, belum jatuh tempo (2 eksemplar).
        $this->borrowing($members[0], $staff, $stocked, ['status' => 'borrowed', 'borrow_date' => today(), 'due_date' => today()->addDays(7)], 2);

        // Terlambat (1 eksemplar belum kembali).
        $this->borrowing($members[1], $staff, $other, ['status' => 'overdue', 'borrow_date' => today()->subDays(14), 'due_date' => today()->subDays(7)], 1);

        // Sudah dikembalikan dengan denda Rp4.000 (sudah dibayar Rp1.000).
        $done = $this->borrowing($members[1], $staff, $other, [
            'status' => 'returned',
            'borrow_date' => today()->subDays(5),
            'due_date' => today()->subDays(2),
        ], 1, 1);

        $return = ReturnTransaction::create([
            'code' => 'KMB-TEST-1',
            'borrowing_id' => $done->id,
            'return_date' => today(),
            'processed_by' => $staff->id,
        ]);
        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'borrowing_item_id' => $done->items()->first()->id,
            'quantity' => 1,
            'due_date' => today()->subDays(2),
            'days_late' => 2,
            'fine_per_day' => 2000,
            'fine_amount' => 4000,
        ]);
        Fine::create([
            'return_item_id' => $returnItem->id,
            'member_id' => $members[1]->id,
            'amount' => 4000,
            'paid_amount' => 1000,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($admin)->get('/super-admin/dashboard')->assertOk();

        $response->assertViewHas('stats', function (array $s) {
            return $s['books'] === 2
                && $s['copies'] === 15
                && $s['available'] === 12
                && $s['members'] === 2
                && $s['admins'] === 2
                && $s['active_loans'] === 2
                && $s['returns'] === 1
                && $s['overdue_books'] === 1
                && $s['fines_total'] === 4000.0
                && $s['fines_unpaid'] === 3000.0;
        });

        $response->assertViewHas('charts', function (array $c) {
            return count($c['borrowings']['labels']) === 12
                && array_sum($c['borrowings']['values']) === 3
                && end($c['returns']['values']) === 1;
        });
    }

    public function test_super_admin_sees_recent_activity(): void
    {
        $admin = User::factory()->superAdmin()->create();
        ActivityLog::record('auth.login', 'Login berhasil uji', null, [], $admin->id);

        $this->actingAs($admin)
            ->get('/super-admin/dashboard')
            ->assertSee('Login berhasil uji')
            ->assertSee($admin->name);
    }

    public function test_admin_master_dashboard_lists_low_stock_books(): void
    {
        Book::factory()->withStock(10, 1)->create(['title' => 'Stok Tipis Uji']);
        Book::factory()->withStock(10, 9)->create(['title' => 'Stok Aman Uji']);

        $this->actingAs(User::factory()->adminMaster()->create())
            ->get('/admin-master/dashboard')
            ->assertOk()
            ->assertSee('Total Kategori')
            ->assertSee('Total Penulis')
            ->assertSee('Total Penerbit')
            ->assertSee('Buku Stok Menipis')
            ->assertViewHas('stats', fn(array $s) => $s['books'] === 2 && $s['low_stock'] === 1)
            ->assertViewHas('lowStockBooks', fn($books) => $books->pluck('title')->all() === ['Stok Tipis Uji']);
    }

    public function test_admin_karyawan_dashboard_counts_todays_work(): void
    {
        $staff = User::factory()->adminKaryawan()->create();
        $member = User::factory()->create(['name' => 'Anggota Menunggu']);
        $book = Book::factory()->create();

        $this->borrowing($member, $staff, $book, ['status' => 'pending', 'processed_by' => null]);
        $this->borrowing($member, $staff, $book, ['status' => 'pending', 'processed_by' => null, 'code' => 'PJM-PENDING-2']);
        $this->borrowing($member, $staff, $book, ['status' => 'borrowed', 'borrow_date' => today(), 'due_date' => today()->addDays(7), 'code' => 'PJM-HARIINI']);

        $this->actingAs($staff)
            ->get('/admin-karyawan/dashboard')
            ->assertOk()
            ->assertSee('Anggota Menunggu')
            ->assertSee('PJM-HARIINI')
            ->assertViewHas('stats', fn(array $s) => $s['pending'] === 2 && $s['loans_today'] === 1 && $s['borrowed_books'] === 1);
    }

    public function test_member_dashboard_only_shows_own_data(): void
    {
        $staff = User::factory()->adminKaryawan()->create();
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Buku Pinjaman Saya']);
        $otherBook = Book::factory()->create(['title' => 'Buku Pinjaman Orang Lain']);

        $this->borrowing($mine, $staff, $book, [
            'code' => 'PJM-MILIKKU',
            'status' => 'borrowed',
            'borrow_date' => today(),
            'due_date' => today()->addDays(2),
        ], 2);
        $this->borrowing($theirs, $staff, $otherBook, [
            'code' => 'PJM-MILIKNYA',
            'status' => 'borrowed',
            'borrow_date' => today(),
            'due_date' => today()->addDays(2),
        ], 3);

        $this->actingAs($mine)
            ->get('/anggota/dashboard')
            ->assertOk()
            ->assertSee('PJM-MILIKKU')
            ->assertSee('Buku Pinjaman Saya')
            ->assertDontSee('PJM-MILIKNYA')
            ->assertDontSee('Buku Pinjaman Orang Lain')
            ->assertViewHas('stats', fn(array $s) => $s['active_books'] === 2 && $s['history'] === 1 && $s['due_soon'] === 1 && $s['overdue'] === 0);
    }

    public function test_member_dashboard_warns_about_overdue_loans_and_unpaid_fines(): void
    {
        $staff = User::factory()->adminKaryawan()->create();
        $member = User::factory()->create();
        $book = Book::factory()->create();

        $this->borrowing($member, $staff, $book, [
            'status' => 'overdue',
            'borrow_date' => today()->subDays(14),
            'due_date' => today()->subDays(3),
        ]);

        $this->actingAs($member)
            ->get('/anggota/dashboard')
            ->assertSee('melewati jatuh tempo')
            ->assertViewHas('stats', fn(array $s) => $s['overdue'] === 1);
    }

    public function test_member_dashboard_works_without_membership_profile_or_transactions(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/anggota/dashboard')
            ->assertOk()
            ->assertSee('Belum terdaftar')
            ->assertSee('Anda belum pernah meminjam buku.');
    }

    public function test_sidebar_links_every_module_and_no_longer_shows_coming_soon(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin/dashboard')
            ->assertSee('Panel Super Admin')
            ->assertDontSee('Segera')
            ->assertSee(route('super-admin.dashboard'), false)
            ->assertSee(route('super-admin.books.index'), false)
            ->assertSee(route('super-admin.borrowings.index'), false)
            ->assertSee(route('super-admin.reports.borrowings'), false);
    }

    public function test_menu_marks_current_dashboard_as_active_for_every_role(): void
    {
        foreach (Role::cases() as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route($role->dashboardRoute()))
                ->assertOk()
                ->assertSee('aria-current="page"', false);
        }
    }

    public function test_menu_has_dashboard_enabled_and_no_dead_links(): void
    {
        foreach (Role::cases() as $role) {
            $groups = Menu::for($role);
            $first = $groups[0]['items'][0];

            $this->assertSame($role->dashboardRoute(), $first['route']);
            $this->assertTrue($first['enabled']);

            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    // Menu yang route-nya belum ada tidak boleh punya URL (tidak bisa diklik).
                    $this->assertSame($item['enabled'], $item['url'] !== null, "Menu {$item['route']} tidak konsisten");
                }
            }
        }
    }
}
