<?php

namespace Database\Seeders;

use App\Models\TelegramScanSetting;
use Illuminate\Database\Seeder;

class TelegramScanSettingSeeder extends Seeder
{
    public function run(): void
    {
        TelegramScanSetting::current();
    }
}
