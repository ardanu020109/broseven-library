<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;
use App\Models\User;
use App\Services\UserSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['admin master' => ['admin-master'], 'admin karyawan' => ['admin-karyawan'], 'anggota' => ['anggota']];
    }

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** @param array<string, mixed> $override */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Pengguna Baru',
            'username' => 'Pengguna.Baru',
            'email' => 'Baru@Example.test',
            'phone' => '081234567890',
            'address' => 'Jl. Uji 1',
            'role' => 'admin-karyawan',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'is_active' => '1',
        ], $override);
    }

    /** @param array<string, mixed> $override */
    private function updatePayload(User $user, array $override = []): array
    {
        return array_merge([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role->value,
        ], $override);
    }

    // ------------------------------------------------------------------ akses

    #[DataProvider('outsiders')]
    public function test_only_super_admin_can_manage_users(string $role): void
    {
        $user = User::factory()->role(Role::from($role))->create();
        $target = User::factory()->create();

        $this->actingAs($user)->get('/super-admin/users')->assertForbidden();
        $this->actingAs($user)->get('/super-admin/users/create')->assertForbidden();
        $this->actingAs($user)->post('/super-admin/users', $this->payload())->assertForbidden();
        $this->actingAs($user)->patch("/super-admin/users/{$target->id}/status")->assertForbidden();
        $this->actingAs($user)->delete("/super-admin/users/{$target->id}")->assertForbidden();

        $this->assertNotSoftDeleted($target);
    }

    public function test_super_admin_can_open_user_pages(): void
    {
        $admin = $this->superAdmin();
        $member = User::factory()->create();

        $this->actingAs($admin)->get('/super-admin/users')->assertOk()->assertSee('Data Pengguna');
        $this->actingAs($admin)->get('/super-admin/users/create')->assertOk()->assertSee('Konfirmasi Password');
        $this->actingAs($admin)->get("/super-admin/users/{$member->id}")->assertOk()->assertSee($member->email);
        $this->actingAs($admin)->get("/super-admin/users/{$member->id}/edit")->assertOk()->assertDontSee('Konfirmasi Password');
    }

    // ------------------------------------------------------------------ tambah

    public function test_staff_account_is_created_with_hashed_password_and_audit_log(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('super-admin.users.store'), $this->payload())->assertSessionDoesntHaveErrors();

        $user = User::where('username', 'pengguna.baru')->firstOrFail();
        $this->assertSame(Role::AdminKaryawan, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('baru@example.test', $user->email);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertNull($user->memberProfile);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'user.created']);
    }

    public function test_member_account_gets_membership_profile(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.users.store'), $this->payload(['role' => 'anggota']));

        $profile = User::where('username', 'pengguna.baru')->firstOrFail()->memberProfile;

        $this->assertNotNull($profile);
        $this->assertMatchesRegularExpression('/^BS\d{2}\d{5}$/', $profile->member_number);
        $this->assertTrue($profile->expired_at->isSameDay(today()->addMonths(12)));
    }

    public function test_account_can_be_created_inactive(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.users.store'), $this->payload(['is_active' => '0']));

        $this->assertFalse(User::where('username', 'pengguna.baru')->firstOrFail()->is_active);
    }

    public function test_validation_rejects_bad_input(): void
    {
        User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@example.test']);

        $this->actingAs($this->superAdmin())->post(route('super-admin.users.store'), $this->payload([
            'username' => 'dipakai',
            'email' => 'dipakai@example.test',
            'role' => 'peretas',
            'password' => 'lemah',
            'password_confirmation' => 'beda',
        ]))->assertSessionHasErrors(['username', 'email', 'role', 'password']);

        $this->assertDatabaseMissing('users', ['name' => 'Pengguna Baru']);
    }

    // ------------------------------------------------------------------ ubah

    public function test_user_can_be_updated_with_photo(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $user = User::factory()->adminKaryawan()->create();

        $this->actingAs($admin)->put(route('super-admin.users.update', $user), $this->updatePayload($user, [
            'name' => 'Nama Diubah',
            'photo' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ]))->assertRedirect(route('super-admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Nama Diubah', $user->name);
        $this->assertStringStartsWith('avatars/', $user->photo);
        Storage::disk('public')->assertExists($user->photo);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.updated']);

        // Ganti foto: file lama dihapus.
        $first = $user->photo;
        $this->actingAs($admin)->put(route('super-admin.users.update', $user), $this->updatePayload($user, [
            'photo' => UploadedFile::fake()->image('baru.png', 200, 200),
        ]));
        Storage::disk('public')->assertMissing($first);
    }

    public function test_user_can_keep_own_email_and_username_on_update(): void
    {
        $user = User::factory()->adminKaryawan()->create();

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.users.update', $user), $this->updatePayload($user, ['name' => 'Sama Saja']))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_update_rejects_email_used_by_someone_else(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->adminKaryawan()->create();

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.users.update', $user), $this->updatePayload($user, ['email' => $other->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_update_cannot_change_status_or_password(): void
    {
        $user = User::factory()->adminKaryawan()->create();
        $oldHash = $user->password;

        $this->actingAs($this->superAdmin())->put(route('super-admin.users.update', $user), $this->updatePayload($user, [
            'is_active' => '0', 'password' => 'DiamDiam123', 'password_confirmation' => 'DiamDiam123',
        ]));

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertSame($oldHash, $user->password);
    }

    // ------------------------------------------------------------------ ganti role

    public function test_role_can_change_for_user_without_borrowing_history_and_creates_profile(): void
    {
        $user = User::factory()->adminKaryawan()->create();

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.users.update', $user), $this->updatePayload($user, ['role' => 'anggota']))
            ->assertSessionDoesntHaveErrors();

        $user->refresh();
        $this->assertSame(Role::Anggota, $user->role);
        $this->assertNotNull($user->memberProfile);
    }

    public function test_role_cannot_change_for_user_with_borrowing_history(): void
    {
        $member = User::factory()->create();
        Borrowing::factory()->create(['member_id' => $member->id, 'status' => 'returned']);

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.users.update', $member), $this->updatePayload($member, ['role' => 'admin-master']))
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Anggota, $member->fresh()->role);
    }

    public function test_own_role_cannot_be_changed(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->put(route('super-admin.users.update', $admin), $this->updatePayload($admin, ['role' => 'anggota']))
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::SuperAdmin, $admin->fresh()->role);
    }

    // ------------------------------------------------------------------ status

    public function test_user_can_be_deactivated_and_reactivated(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('super-admin.users.toggle-status', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.status_changed']);

        $this->actingAs($admin)->patch(route('super-admin.users.toggle-status', $user))->assertSessionHas('success');
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->superAdmin())->patch(route('super-admin.users.toggle-status', $user));
        auth()->logout();

        $this->post('/login', ['login' => $user->username, 'password' => 'Password123!'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_own_account_cannot_be_deactivated(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch(route('super-admin.users.toggle-status', $admin))->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    // ------------------------------------------------------------------ reset password

    public function test_password_can_be_reset_and_is_validated(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('super-admin.users.reset-password', $user), [
            'password' => 'lemah', 'password_confirmation' => 'lemah',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));

        $this->actingAs($admin)->put(route('super-admin.users.reset-password', $user), [
            'password' => 'BaruBanget1', 'password_confirmation' => 'BaruBanget1',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('BaruBanget1', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_reset']);
    }

    // ------------------------------------------------------------------ hapus

    public function test_user_is_soft_deleted_and_cannot_login_afterwards(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete(route('super-admin.users.destroy', $user))
            ->assertRedirect(route('super-admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted']);

        auth()->logout();
        $this->post('/login', ['login' => $user->username, 'password' => 'Password123!'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_own_account_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->delete(route('super-admin.users.destroy', $admin))->assertSessionHas('error');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_user_with_open_borrowing_or_unpaid_fine_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();

        $borrower = User::factory()->create();
        Borrowing::factory()->create(['member_id' => $borrower->id, 'status' => 'pending']);

        $debtor = User::factory()->create();
        $finished = Borrowing::factory()->create(['member_id' => $debtor->id, 'status' => 'returned']);
        $return = ReturnTransaction::create(['code' => 'KMB-T-1', 'borrowing_id' => $finished->id, 'return_date' => today()]);
        $item = \App\Models\BorrowingItem::factory()->create(['borrowing_id' => $finished->id]);
        $returnItem = ReturnItem::create([
            'return_id' => $return->id, 'borrowing_item_id' => $item->id, 'quantity' => 1,
            'due_date' => today()->subDays(2), 'days_late' => 2, 'fine_per_day' => 2000, 'fine_amount' => 4000,
        ]);
        Fine::create(['return_item_id' => $returnItem->id, 'member_id' => $debtor->id, 'amount' => 4000, 'status' => 'unpaid']);

        $this->actingAs($admin)->delete(route('super-admin.users.destroy', $borrower))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('super-admin.users.destroy', $debtor))->assertSessionHas('error');

        $this->assertNotSoftDeleted($borrower);
        $this->assertNotSoftDeleted($debtor);
    }

    public function test_user_with_finished_history_can_be_deleted_and_history_is_kept(): void
    {
        $member = User::factory()->create();
        $borrowing = Borrowing::factory()->create(['member_id' => $member->id, 'status' => 'returned']);

        $this->actingAs($this->superAdmin())->delete(route('super-admin.users.destroy', $member))->assertSessionHas('success');

        $this->assertSoftDeleted($member);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'member_id' => $member->id]);
    }

    public function test_last_active_super_admin_is_protected_by_the_safety_service(): void
    {
        $safety = app(UserSafetyService::class);
        $only = $this->superAdmin();

        $this->assertTrue($safety->isLastActiveSuperAdmin($only));
        $this->assertNotNull($safety->deletionBlocker($only, User::factory()->create()));
        $this->assertNotNull($safety->deactivationBlocker($only, User::factory()->create()));
        $this->assertNotNull($safety->roleChangeBlocker($only, Role::Anggota, User::factory()->create()));

        $second = $this->superAdmin();
        $this->assertFalse($safety->isLastActiveSuperAdmin($only));
        $this->assertNull($safety->deletionBlocker($second, $only));
    }

    // ------------------------------------------------------------------ daftar

    public function test_user_list_search_filters_and_pagination(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'Zainal Unik', 'username' => 'zainal', 'email' => 'zainal@example.test']);
        User::factory()->adminMaster()->create(['name' => 'Master Uji', 'username' => 'masteruji']);
        User::factory()->inactive()->create(['name' => 'Nonaktif Uji', 'username' => 'nonaktifuji']);

        $this->actingAs($admin)->get('/super-admin/users?q=zainal')->assertSee('Zainal Unik')->assertDontSee('Master Uji');
        $this->actingAs($admin)->get('/super-admin/users?q=zainal@example')->assertSee('Zainal Unik');
        $this->actingAs($admin)->get('/super-admin/users?role=admin-master')->assertSee('Master Uji')->assertDontSee('Zainal Unik');
        $this->actingAs($admin)->get('/super-admin/users?status=inactive')->assertSee('Nonaktif Uji')->assertDontSee('Zainal Unik');
        $this->actingAs($admin)->get('/super-admin/users?role=ngawur&status=x')->assertOk();

        User::factory()->count(12)->create();
        $this->actingAs($admin)->get('/super-admin/users')
            ->assertSee('page-link')
            ->assertViewHas('users', fn ($users) => $users->count() === 10);
    }

    public function test_password_hash_is_never_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->superAdmin())->get("/super-admin/users/{$user->id}")->assertDontSee($user->password, false);
    }

    public function test_menu_item_for_users_is_enabled(): void
    {
        $this->actingAs($this->superAdmin())->get('/super-admin/dashboard')
            ->assertSee(route('super-admin.users.index'), false);
    }
}
