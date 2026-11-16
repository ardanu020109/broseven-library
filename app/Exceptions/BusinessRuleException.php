<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis (stok habis, status salah, dsb). Pesannya ditujukan untuk pengguna. */
class BusinessRuleException extends RuntimeException
{
}
