<?php

namespace Database\Seeders;

use App\Models\Keyword;
use Illuminate\Database\Seeder;

/**
 * Keywords for appliance repair leads (fridges + washing machines).
 * Only appliance names — intent/symptom words are too noisy for substring match.
 */
class KeywordSeeder extends Seeder
{
    public function run(): void
    {
        $keep = [
            ['word' => 'холодильник', 'type' => 'both'],
            ['word' => 'холодильника', 'type' => 'both'],
            ['word' => 'стиралка', 'type' => 'both'],
            ['word' => 'стиральную', 'type' => 'both'],
            ['word' => 'стиральная', 'type' => 'both'],
            ['word' => 'стиральной', 'type' => 'both'],
            ['word' => 'машинка', 'type' => 'both'],
            ['word' => 'машинку', 'type' => 'both'],
        ];

        $keepWords = array_column($keep, 'word');

        // Drop noisy keywords left from earlier seeders
        Keyword::query()->whereNotIn('word', $keepWords)->delete();

        foreach ($keep as $row) {
            Keyword::query()->updateOrCreate(
                ['word' => $row['word']],
                ['type' => $row['type']],
            );
        }
    }
}
