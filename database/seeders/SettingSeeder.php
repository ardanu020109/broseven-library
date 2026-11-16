<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('member_validity_months', 12);
        Setting::set('low_stock_threshold', 2);
    }
}
