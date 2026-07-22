<?php

namespace Database\Seeders;

use App\Models\VkGroup;
use Illuminate\Database\Seeder;

class VkGroupSeeder extends Seeder
{
    /**
     * Seed VK groups to scan for leads.
     */
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Подслушано Инорс',
                'url' => 'https://vk.com/v_inorse',
                'active' => true,
            ],
            [
                'name' => 'Халтура Уфа| Подработка в Уфе| Ежедневная оплата',
                'url' => 'https://vk.com/halturaufa',
                'active' => true,
            ],
        ];

        foreach ($groups as $group) {
            VkGroup::query()->updateOrCreate(
                ['url' => $group['url']],
                [
                    'name' => $group['name'],
                    'active' => $group['active'],
                ]
            );
        }
    }
}
