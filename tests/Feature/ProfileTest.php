<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['super admin' => ['super-admin'], 'admin master' => ['admin-master'], 'admin karyawan' => ['admin-karyawan'], 'anggota' => ['anggota']];
    }

    private function userFor(string $role): User
    {
        return $role === 'anggota' ? $this->makeMember(['name' => 'Anggota Profil']) : User::factory()->role(Role::from($role))->create(['name' => 'Staf Profil']);
    }

    #[DataProvider('roles')]
    public function test_every_role_can_open_its_profile_page(string $role): void
    {
        $user = $this->userFor($role);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->name)->assertSee($user->email)->assertSee('Ganti Password');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
        $this->put('/profile', ['name' => 'x'])->assertRedirect(route('login'));
        $this->put('/profile/password', [])->assertRedirect(route('login'));
    }

    public function test_member_profile_shows_membership_and_card_link(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)->get('/profile')
            ->assertSee($member->memberProfile->member_number)
            ->assertSee(route('anggota.membership-card.show'), false);
    }

    public function test_profile_data_can_be_updated_but_identity_fields_cannot(): void
    {
        $member = $this->makeMember();
        $email = $member->email;
        $username = $member->username;

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => 'Nama Baru', 'phone' => '081234567890', 'address' => 'Jl. Baru 1',
            'email' => 'penyusup@example.test', 'username' => 'penyusup', 'role' => 'super-admin', 'is_active' => '0',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $member->refresh();
        $this->assertSame('Nama Baru', $member->name);
        $this->assertSame('081234567890', $member->phone);
        $this->assertSame($email, $member->email);
        $this->assertSame($username, $member->username);
        $this->assertSame(Role::Anggota, $member->role);
        $this->assertTrue($member->is_active);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $member->id, 'action' => 'user.profile_updated']);
    }

    public function test_profile_validation(): void
    {
        $this->actingAs($this->makeMember())->put(route('profile.update'), ['name' => '', 'phone' => 'abc'])
            ->assertSessionHasErrors(['name', 'phone']);
    }

    public function test_photo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $member = $this->makeMember();

        $this->actingAs($member)->put(route('profile.update'), ['name' => 'Berfoto', 'photo' => UploadedFile::fake()->image('satu.jpg', 200, 200)]);
        $first = $member->fresh()->photo;
        Storage::disk('public')->assertExists($first);

        $this->actingAs($member)->put(route('profile.update'), ['name' => 'Berfoto', 'photo' => UploadedFile::fake()->image('dua.png', 200, 200)]);
        $second = $member->fresh()->photo;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($member)->put(route('profile.update'), ['name' => 'Berfoto', 'remove_photo' => '1']);
        $this->assertNull($member->fresh()->photo);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_password_can_be_changed_with_the_current_password(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)->put(route('profile.password'), [
            'current_password' => 'Password123!', 'password' => 'BaruBanget1', 'password_confirmation' => 'BaruBanget1',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('BaruBanget1', $member->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $member->id, 'action' => 'auth.password_changed']);

        auth()->logout();
        $this->post('/login', ['login' => $member->username, 'password' => 'BaruBanget1'])->assertRedirect(route('anggota.dashboard'));
    }

    public function test_password_change_is_refused_for_wrong_weak_or_unchanged_passwords(): void
    {
        $member = $this->makeMember();
        $put = fn (array $data) => $this->actingAs($member)->put(route('profile.password'), $data);

        $put(['current_password' => 'salah-total', 'password' => 'BaruBanget1', 'password_confirmation' => 'BaruBanget1'])
            ->assertSessionHasErrors('current_password');
        $put(['current_password' => 'Password123!', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertSessionHasErrors('password');
        $put(['current_password' => 'Password123!', 'password' => 'BaruBanget1', 'password_confirmation' => 'Beda12345'])
            ->assertSessionHasErrors('password');
        $put(['current_password' => 'Password123!', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Password123!', $member->fresh()->password));
    }

    public function test_profile_menu_item_and_dropdown_link_are_enabled(): void
    {
        $this->actingAs($this->makeMember())->get('/anggota/dashboard')
            ->assertSee(route('profile.edit'), false)
            ->assertDontSee('Segera');
    }
}
