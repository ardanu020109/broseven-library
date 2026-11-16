<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Matriks 4x4: setiap role mencoba membuka dashboard semua role.
     *
     * @return array<string, array{string, string}>
     */
    public static function matrix(): array
    {
        $roles = ['super-admin', 'admin-master', 'admin-karyawan', 'anggota'];
        $cases = [];

        foreach ($roles as $actor) {
            foreach ($roles as $target) {
                $cases["{$actor} membuka {$target}"] = [$actor, $target];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_can_only_open_its_own_area(string $actor, string $target): void
    {
        $user = User::factory()->role(Role::from($actor))->create();

        $response = $this->actingAs($user)->get("/{$target}/dashboard");

        if ($actor === $target) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    public function test_forbidden_attempt_is_written_to_audit_log(): void
    {
        $user = User::factory()->create(); // anggota

        $this->actingAs($user)->get('/super-admin/dashboard')->assertForbidden();

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.forbidden']);
    }

    public function test_forbidden_page_shows_friendly_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin-master/dashboard')
            ->assertForbidden()
            ->assertSee('Akses Ditolak');
    }

    public function test_dashboard_shortcut_redirects_to_own_dashboard(): void
    {
        $user = User::factory()->adminMaster()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('admin-master.dashboard'));
    }

    public function test_deactivated_user_with_active_session_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/anggota/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
