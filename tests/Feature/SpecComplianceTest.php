<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class SpecComplianceTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin'],
            'admin master' => ['admin-master'],
            'admin karyawan' => ['admin-karyawan'],
            'anggota' => ['anggota'],
        ];
    }

    /** @return array<string, array{string, array<int, string>}> */
    public static function forbiddenAreas(): array
    {
        return [
            'admin master' => ['admin-master', [
                '/super-admin/users', '/super-admin/blogs', '/super-admin/activity-logs', '/super-admin/library-profile',
                '/super-admin/reports/borrowings', '/admin-karyawan/borrowings', '/admin-karyawan/returns',
                '/admin-karyawan/fines', '/admin-karyawan/members', '/super-admin/borrowings', '/anggota/dashboard',
            ]],
            'admin karyawan' => ['admin-karyawan', [
                '/admin-master/books', '/admin-master/categories', '/admin-master/authors', '/admin-master/publishers',
                '/admin-master/shelves', '/super-admin/users', '/super-admin/blogs', '/super-admin/reports/returns',
                '/super-admin/library-profile', '/anggota/catalog', '/anggota/fines',
            ]],
            'anggota' => ['anggota', [
                '/super-admin/dashboard', '/admin-master/dashboard', '/admin-karyawan/dashboard', '/admin-master/books',
                '/admin-karyawan/borrowings', '/admin-karyawan/returns', '/admin-karyawan/fines', '/super-admin/users',
                '/super-admin/reports/borrowings', '/super-admin/activity-logs',
            ]],
        ];
    }

    /** Setiap menu di sidebar sudah punya halaman yang berfungsi (tidak ada lagi "Segera"). */
    #[DataProvider('roles')]
    public function test_every_sidebar_item_is_built_and_opens(string $roleValue): void
    {
        $role = Role::from($roleValue);
        $this->makeLibraryProfile();

        $user = $role === Role::Anggota ? $this->makeMember() : $this->makeStaff($roleValue);
        $this->actingAs($user);

        $items = collect(Menu::for($role))->flatMap(fn (array $group) => $group['items']);
        $this->assertGreaterThan(3, $items->count());

        foreach ($items as $item) {
            $this->assertTrue($item['enabled'], "Menu \"{$item['label']}\" ({$item['route']}) belum memiliki route.");
            $this->get($item['url'])->assertOk();
        }
    }

    /** @param array<int, string> $urls */
    #[DataProvider('forbiddenAreas')]
    public function test_role_cannot_open_other_areas(string $roleValue, array $urls): void
    {
        $user = $roleValue === 'anggota' ? $this->makeMember() : $this->makeStaff($roleValue);

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_super_admin_can_also_open_create_forms_of_every_master_module(): void
    {
        $admin = $this->makeStaff('super-admin');

        foreach (['books', 'categories', 'authors', 'publishers', 'shelves'] as $module) {
            $this->actingAs($admin)->get("/super-admin/{$module}/create")->assertOk();
        }

        foreach (['users', 'blogs'] as $module) {
            $this->actingAs($admin)->get("/super-admin/{$module}/create")->assertOk();
        }
    }

    public function test_guest_pages_listed_in_the_spec_are_public(): void
    {
        foreach (['/', '/tentang-kami', '/katalog', '/blog', '/kontak', '/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
