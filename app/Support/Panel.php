<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

final class Panel
{
    /** Prefix route sama dengan nilai role: "super-admin" atau "admin-master". */
    public static function prefix(): string
    {
        return Auth::user()->role->value;
    }

    /** route('{role}.{name}'), misalnya Panel::route('categories.index'). */
    public static function route(string $name, mixed $parameters = []): string
    {
        return route(self::prefix() . '.' . $name, $parameters);
    }

    /** Nama layout sesuai role: "layouts.super-admin" atau "layouts.admin-master". */
    public static function layout(): string
    {
        return 'layouts.' . self::prefix();
    }
}
