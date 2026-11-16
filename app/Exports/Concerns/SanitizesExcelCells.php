<?php

namespace App\Exports\Concerns;

trait SanitizesExcelCells
{
    /**
     * Teks yang diawali = + - @ dianggap rumus oleh Excel (formula injection).
     * Diberi awalan apostrof agar selalu dibaca sebagai teks biasa.
     */
    protected function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $value
            : $value;
    }
}
