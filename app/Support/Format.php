<?php

namespace App\Support;

final class Format
{
    /** 2000 menjadi "Rp2.000". */
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp' . number_format((float) $amount, 0, ',', '.');
    }

    /** 12500 menjadi "12.500". */
    public static function number(float|int|string|null $number): string
    {
        return number_format((float) $number, 0, ',', '.');
    }
}
