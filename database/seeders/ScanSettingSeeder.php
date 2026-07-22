<?php

namespace Database\Seeders;

use App\Models\ScanSetting;
use Illuminate\Database\Seeder;

class ScanSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = ScanSetting::defaultAttributes();

        $row = ScanSetting::query()->firstOrNew(['name' => ScanSetting::NAME_DEFAULT]);

        if (! $row->exists) {
            $row->fill($defaults)->save();
            $this->command?->info('Scan settings created with competitive defaults (every 30 min).');

            return;
        }

        // Do not overwrite operator-tuned values on re-seed; only fill nulls / missing columns.
        $this->command?->info('Scan settings already exist (id='.$row->id.'); left unchanged.');
    }
}
