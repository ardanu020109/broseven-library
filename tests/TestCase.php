<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Diperiksa SEBELUM aplikasi dan migrasi berjalan, supaya database asli tidak pernah tersentuh.
        $this->guardTestEnvironment();

        parent::setUp();

        // Proteksi CSRF diuji secara terpisah di browser; test HTTP tidak membawa token.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function guardTestEnvironment(): void
    {
        $read = static function (string $key): ?string {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

            return is_string($value) ? $value : null;
        };

        $problems = [];

        if ($read('APP_ENV') !== 'testing') {
            $problems[] = 'APP_ENV bernilai "' . ($read('APP_ENV') ?? '(kosong)') . '", seharusnya "testing"';
        }

        if ($read('DB_CONNECTION') !== 'sqlite' || $read('DB_DATABASE') !== ':memory:') {
            $problems[] = 'database bukan SQLite in-memory (DB_CONNECTION=' . ($read('DB_CONNECTION') ?? '-') . ', DB_DATABASE=' . ($read('DB_DATABASE') ?? '-') . ')';
        }

        if ($problems !== []) {
            throw new RuntimeException(
                'Test dihentikan demi keamanan data: ' . implode('; ', $problems)
                . '. Periksa phpunit.xml dan variabel environment sistem (Get-ChildItem Env:).'
            );
        }
    }
}
