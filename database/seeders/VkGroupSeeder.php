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
            // === Мамочки ===
            [
                'name' => 'Мамы Уфы. Мамочки Уфа',
                'url' => 'https://vk.com/mamyufa',
                'active' => true,
            ],
            [
                'name' => 'Мамочки УФЫ / Уфа мама',
                'url' => 'https://vk.com/ya__ufa',
                'active' => true,
            ],
            [
                'name' => 'Клуб Мам Уфы',
                'url' => 'https://vk.com/ufakid',
                'active' => true,
            ],
            [
                'name' => 'Мамы Уфы Мамочки Уфа',
                'url' => 'https://vk.com/mama_ufi',
                'active' => true,
            ],
            [
                'name' => 'Клуб мам Уфы',
                'url' => 'https://vk.com/clubmamyfa',
                'active' => true,
            ],
            [
                'name' => 'Подслушано у Мам / Уфа',
                'url' => 'https://vk.com/ufa_mama1',
                'active' => true,
            ],
            [
                'name' => 'Уфа, Мамочки Уфы (объявления)',
                'url' => 'https://vk.com/ufa_mamochki_rb',
                'active' => true,
            ],
            [
                'name' => 'Мамочки Сипайлово',
                'url' => 'https://vk.com/clubmamochki_sipailovo',
                'active' => true,
            ],

            // === Крупные барахолки ===
            [
                'name' => 'Барахолка Уфа (Экспресс в РБ)',
                'url' => 'https://vk.com/biznesrb',
                'active' => true,
            ],
            [
                'name' => 'Куплю/Продам Уфа Объявления Барахолка',
                'url' => 'https://vk.com/baraholka_v_ufe',
                'active' => true,
            ],
            [
                'name' => 'Черный Рынок Уфа',
                'url' => 'https://vk.com/bmufa',
                'active' => true,
            ],
            [
                'name' => 'Барахолка Уфа Купи-Продай',
                'url' => 'https://vk.com/baraholka_kupi_prodai',
                'active' => true,
            ],
            [
                'name' => 'Куплю продам Уфа барахолка',
                'url' => 'https://vk.com/kuplyprodamufa',
                'active' => true,
            ],
            [
                'name' => 'Отдам Даром Уфа',
                'url' => 'https://vk.com/otdam_darom5',
                'active' => true,
            ],
            [
                'name' => 'Барахолка Уфа, Шакша, Иглино',
                'url' => 'https://vk.com/baraholka.ufa.shaksha.iglino',
                'active' => true,
            ],

            // === Подслушано и городские ===
            [
                'name' => 'Подслушано Уфа',
                'url' => 'https://vk.com/ufa_overhear',
                'active' => true,
            ],
            [
                'name' => 'Уфа подслушано',
                'url' => 'https://vk.com/ufa_sluhi',
                'active' => true,
            ],

            // === Районные ===
            [
                'name' => 'Сипайлово Онлайн',
                'url' => 'https://vk.com/sipaylovo',
                'active' => true,
            ],
            [
                'name' => 'Подслушано Сипайлово',
                'url' => 'https://vk.com/sipaufa',
                'active' => true,
            ],
            [
                'name' => 'Подслушано Черниковка',
                'url' => 'https://vk.com/chernikovka_ufa',
                'active' => true,
            ],
            [
                'name' => 'Подслушано Инорс',
                'url' => 'https://vk.com/inorz',
                'active' => true,
            ],
            [
                'name' => 'Подслушано Инорс (v_inorse)',
                'url' => 'https://vk.com/v_inorse',
                'active' => true,
            ],
            [
                'name' => 'Подслушано Шакша',
                'url' => 'https://vk.com/shaksha_rb',
                'active' => true,
            ],
            [
                'name' => 'Демский Базар',
                'url' => 'https://vk.com/dema_bazar',
                'active' => true,
            ],
            [
                'name' => 'Барахолка/Уфа',
                'url' => 'https://vk.com/gorod_ufa102',
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
