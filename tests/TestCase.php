<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Diperiksa tepat setelah aplikasi dibuat dan SEBELUM trait seperti RefreshDatabase
     * menjalankan migrasi, sehingga database asli tidak pernah tersentuh.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.default');
        $problems = [];

        if ($app->configurationIsCached()) {
            $problems[] = 'konfigurasi sedang di-cache (jalankan: php artisan config:clear)';
        }

        if (! $app->environment('testing')) {
            $problems[] = 'environment bernilai "' . $app->environment() . '", seharusnya "testing"';
        }

        if ($database !== 'sqlite' || $app['config']->get("database.connections.{$database}.database") !== ':memory:') {
            $problems[] = "database yang dipakai \"{$database}\", seharusnya SQLite in-memory";
        }

        if ($problems !== []) {
            throw new RuntimeException(
                'Test dihentikan demi keamanan data: ' . implode('; ', $problems)
                . '. Periksa phpunit.xml dan pastikan bootstrap\\cache\\config.php tidak ada.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Proteksi CSRF dimatikan untuk test HTTP (test tidak membawa token).
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
