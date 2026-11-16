<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_html_pages_carry_security_headers_and_a_strict_csp(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
        $this->assertStringNotContainsString('http://', $csp);
        $this->assertStringNotContainsString('https://', $csp); // tidak ada asal eksternal
    }

    public function test_headers_are_also_present_on_authenticated_and_error_pages(): void
    {
        $this->actingAs($this->makeMember())->get('/anggota/dashboard')->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csp_is_skipped_for_pdf_responses_but_other_headers_remain(): void
    {
        $this->makeLibraryProfile();
        $staff = $this->makeStaff();
        $fine = $this->makeFine($this->makeMember(), $staff);
        $returnId = $fine->returnItem->return_id;

        $response = $this->actingAs($staff)->get("/admin-karyawan/returns/{$returnId}/receipt");

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_csp_can_be_disabled_by_configuration(): void
    {
        config(['library.csp_enabled' => false]);

        $this->get('/')->assertOk()->assertHeaderMissing('Content-Security-Policy')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_is_sent_only_for_https_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('https://broseven.test/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://broseven.test/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_global_rate_limiter_is_registered(): void
    {
        $this->assertNotNull(RateLimiter::limiter('web'));
    }

    /** route:cache gagal bila ada route berbasis Closure. */
    public function test_application_routes_do_not_use_closures(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $framework = $route->uri() === 'up' || str_starts_with((string) $route->getName(), 'storage.');

            if (! $framework && $route->getActionName() === 'Closure') {
                $offenders[] = $route->uri();
            }
        }

        $this->assertSame([], $offenders, 'Route memakai Closure: ' . implode(', ', $offenders));
    }

    public function test_sensitive_attributes_cannot_be_mass_assigned(): void
    {
        $user = new User();
        $user->fill(['name' => 'X', 'role' => 'super-admin', 'is_active' => false]);
        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('is_active', $user->getAttributes());

        $book = new Book();
        $book->fill(['title' => 'T', 'total_stock' => 99, 'available_stock' => 99, 'borrowed_stock' => 9]);
        foreach (['total_stock', 'available_stock', 'borrowed_stock'] as $column) {
            $this->assertArrayNotHasKey($column, $book->getAttributes());
        }
    }

    public function test_passwords_are_always_hashed(): void
    {
        $user = app(MemberService::class)->register([
            'name' => 'Anggota Hash', 'username' => 'anggota.hash', 'email' => 'hash@example.test', 'password' => 'Rahasia123',
        ]);

        $this->assertNotSame('Rahasia123', $user->password);
        $this->assertContains(Hash::info($user->password)['algoName'], ['bcrypt', 'argon', 'argon2id']);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_dangerous_or_oversized_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $member = $this->makeMember();

        $attempts = [
            'dimensi raksasa' => UploadedFile::fake()->image('besar.jpg', 4001, 10),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'php berkedok jpg' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>'),
            'ekstensi php' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            'pdf' => UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'),
        ];

        foreach ($attempts as $label => $file) {
            $this->actingAs($member)->put(route('profile.update'), ['name' => 'Nama Uji', 'photo' => $file])
                ->assertSessionHasErrors('photo', null, "Upload '{$label}' seharusnya ditolak.");
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_uploaded_files_get_random_names_under_their_own_folder(): void
    {
        Storage::fake('public');
        $member = $this->makeMember();

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => 'Nama Uji',
            'photo' => UploadedFile::fake()->image('../../nama-nakal.png', 100, 100),
        ])->assertSessionDoesntHaveErrors();

        $path = $member->fresh()->photo;
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringNotContainsString('nama-nakal', $path);
        $this->assertStringNotContainsString('..', $path);
    }
}
