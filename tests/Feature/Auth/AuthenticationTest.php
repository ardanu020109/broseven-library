<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password123!';

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

    public function test_login_page_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    #[DataProvider('roles')]
    public function test_each_role_is_redirected_to_its_own_dashboard(string $roleValue): void
    {
        $role = Role::from($roleValue);
        $user = User::factory()->role($role)->create();

        $response = $this->post('/login', ['login' => $user->username, 'password' => self::PASSWORD]);

        $response->assertRedirect(route($role->dashboardRoute()));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('anggota.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_writes_an_audit_log(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->username, 'password' => self::PASSWORD]);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['login' => $user->username, 'password' => 'salah-total'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login_failed']);
    }

    public function test_unknown_account_gets_same_error_as_wrong_password(): void
    {
        $this->post('/login', ['login' => 'tidak-ada', 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['login' => 'Username/email atau password salah.']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['login' => $user->username, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->username, 'password' => 'salah']);
        }

        // Percobaan ke-6 ditolak walaupun password benar.
        $this->post('/login', ['login' => $user->username, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_guest_is_redirected_to_login_from_protected_page(): void
    {
        $this->get('/anggota/dashboard')->assertRedirect(route('login'));
    }

    public function test_logged_in_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->adminKaryawan()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('admin-karyawan.dashboard'));
    }

    public function test_registration_creates_member_with_profile_and_ignores_role_field(): void
    {
        $response = $this->post('/register', [
            'name' => 'Budi Penguji',
            'username' => 'Budi.Penguji',
            'email' => 'Budi.Penguji@Example.test',
            'phone' => '081234567890',
            'address' => 'Jl. Uji No. 1',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'role' => 'super-admin', // upaya privilege escalation
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('anggota.dashboard'));

        $user = User::where('email', 'budi.penguji@example.test')->firstOrFail();

        $this->assertSame('budi.penguji', $user->username);
        $this->assertSame(Role::Anggota, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);

        $profile = $user->memberProfile;
        $this->assertNotNull($profile);
        $this->assertMatchesRegularExpression('/^BS\d{2}\d{5}$/', $profile->member_number);
        $this->assertSame(64, strlen($profile->card_token));
        $this->assertTrue($profile->is_valid);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test', 'username' => 'dipakai']);

        $this->post('/register', [
            'name' => '',
            'username' => 'dipakai',
            'email' => 'dipakai@example.test',
            'password' => 'lemah',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors(['name', 'username', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_reset_link_is_sent_to_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_same_message_and_nothing_is_sent(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaru1',
            'password_confirmation' => 'PasswordBaru1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('PasswordBaru1', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.password_reset']);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'token-ngawur',
            'email' => $user->email,
            'password' => 'PasswordBaru1',
            'password_confirmation' => 'PasswordBaru1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }
}
