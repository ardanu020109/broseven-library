<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CountsQueries;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class QueryEfficiencyTest extends TestCase
{
    use CountsQueries;
    use CreatesLibraryData;
    use RefreshDatabase;

    /** Peminjaman berjalan, masing-masing dengan 2 judul buku. */
    private function loans(User $staff, int $count, ?User $member = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $books = Book::factory()->withStock(5, 3)->count(2)->create();
            $this->makeBorrowed($member ?? $this->makeMember(), $staff, $books->all());
        }
    }

    public function test_guest_catalog(): void
    {
        Book::factory()->count(3)->create();

        $this->assertConstantQueries('/katalog', null, fn () => Book::factory()->count(9)->create());
    }

    public function test_guest_home(): void
    {
        Book::factory()->count(3)->create();
        Blog::factory()->count(1)->create();

        $this->assertConstantQueries('/', null, function () {
            Book::factory()->count(9)->create();
            Blog::factory()->count(2)->create();
        });
    }

    public function test_guest_blog_list(): void
    {
        Blog::factory()->count(3)->create();

        $this->assertConstantQueries('/blog', null, fn () => Blog::factory()->count(6)->create());
    }

    public function test_admin_book_list(): void
    {
        Book::factory()->count(3)->create();

        $this->assertConstantQueries('/admin-master/books', $this->makeStaff('admin-master'), fn () => Book::factory()->count(7)->create());
    }

    public function test_staff_borrowing_list(): void
    {
        $staff = $this->makeStaff();
        $this->loans($staff, 3);

        $this->assertConstantQueries('/admin-karyawan/borrowings', $staff, fn () => $this->loans($staff, 7));
    }

    public function test_staff_active_borrowed_books_list(): void
    {
        $staff = $this->makeStaff();
        $this->loans($staff, 3);

        $this->assertConstantQueries('/admin-karyawan/borrowings/active', $staff, fn () => $this->loans($staff, 7));
    }

    public function test_return_search_page(): void
    {
        $staff = $this->makeStaff();
        $this->loans($staff, 3);

        $this->assertConstantQueries('/admin-karyawan/returns', $staff, fn () => $this->loans($staff, 7));
    }

    public function test_fine_list(): void
    {
        $staff = $this->makeStaff();
        for ($i = 0; $i < 3; $i++) {
            $this->makeFine($this->makeMember(), $staff, 4000);
        }

        $this->assertConstantQueries('/admin-karyawan/fines', $staff, function () use ($staff) {
            for ($i = 0; $i < 7; $i++) {
                $this->makeFine($this->makeMember(), $staff, 4000);
            }
        });
    }

    public function test_member_list_for_staff(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeMember();
        }

        $this->assertConstantQueries('/admin-karyawan/members', $this->makeStaff(), function () {
            for ($i = 0; $i < 7; $i++) {
                $this->makeMember();
            }
        });
    }

    public function test_user_list_for_super_admin(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeMember();
        }

        $this->assertConstantQueries('/super-admin/users', $this->makeStaff('super-admin'), function () {
            for ($i = 0; $i < 6; $i++) {
                $this->makeMember();
            }
        });
    }

    public function test_activity_log_list(): void
    {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            ActivityLog::record('auth.login', 'Login', null, [], $user->id);
        }

        $this->assertConstantQueries('/super-admin/activity-logs', $this->makeStaff('super-admin'), function () {
            foreach (User::factory()->count(12)->create() as $user) {
                ActivityLog::record('auth.login', 'Login', null, [], $user->id);
            }
        });
    }

    public function test_reports(): void
    {
        $staff = $this->makeStaff();
        $admin = $this->makeStaff('super-admin');
        $this->loans($staff, 3);

        $this->assertConstantQueries('/super-admin/reports/borrowings?period=all', $admin, fn () => $this->loans($staff, 6));
    }

    public function test_member_borrowing_list(): void
    {
        $staff = $this->makeStaff();
        $member = $this->makeMember();
        $this->loans($staff, 2, $member);

        $this->assertConstantQueries('/anggota/borrowings', $member, fn () => $this->loans($staff, 6, $member));
    }

    public function test_super_admin_dashboard(): void
    {
        $staff = $this->makeStaff();
        $this->loans($staff, 3);

        $this->assertConstantQueries('/super-admin/dashboard', $this->makeStaff('super-admin'), function () use ($staff) {
            $this->loans($staff, 6);
            foreach (User::factory()->count(8)->create() as $user) {
                ActivityLog::record('auth.login', 'Login', null, [], $user->id);
            }
        });
    }

    public function test_staff_dashboard(): void
    {
        $staff = $this->makeStaff();

        for ($i = 0; $i < 3; $i++) {
            $this->makePending($this->makeMember(), [Book::factory()->create()]);
        }

        $this->assertConstantQueries('/admin-karyawan/dashboard', $staff, function () {
            for ($i = 0; $i < 5; $i++) {
                $this->makePending($this->makeMember(), [Book::factory()->create()]);
            }
        });
    }
}
