<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UsernamePatternTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function valid(): array
    {
        return array_map(fn (string $v) => [$v], [
            'budi.santoso' => 'budi.santoso', 'siti_rahayu' => 'siti_rahayu', 'a-b-c' => 'a-b-c',
            'user123' => 'user123', 'x.y.z' => 'x.y.z', 'a1' => 'a1',
        ]);
    }

    /** @return array<string, array{string}> */
    public static function invalid(): array
    {
        return array_map(fn (string $v) => [$v], [
            'awalan titik' => '.budi', 'akhiran titik' => 'budi.', 'titik berurutan' => 'bu..di',
            'spasi' => 'bu di', 'simbol' => 'budi@x', 'huruf besar' => 'BUDI',
            'non-ASCII' => 'ünal', 'kosong' => '', 'awalan garis bawah' => '_abc',
        ]);
    }

    #[DataProvider('valid')]
    public function test_valid_usernames_match(string $username): void
    {
        $this->assertSame(1, preg_match(User::USERNAME_PATTERN, $username));
    }

    #[DataProvider('invalid')]
    public function test_invalid_usernames_do_not_match(string $username): void
    {
        $this->assertSame(0, preg_match(User::USERNAME_PATTERN, $username));
    }
}
