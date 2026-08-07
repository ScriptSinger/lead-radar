<?php

namespace Database\Seeders;

use App\Models\TelegramChannel;
use Illuminate\Database\Seeder;

class TelegramChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['Уфа | Заявки для мастеров', 'RabotaRukamiUFA'], ['РАБОТА | РАБОЧИЕ УФА | Халтура', 'rabotudamufa'], ['ХАЛТУРА УФА / Работа / Шабашка', 'ufahaltura'], ['Стройка и Ремонт Уфа РБ', 'stroikaRemontRb102'], ['ШАБАШ UFA / Муж на час / Услуги', 'shabaschka'], ['Уфа — Подработка / Шабашка', 'ufa_akaunt'], ['Няни / Уборка / Ремонт / поиск работы Уфа', 'nyaniufa'], ['Уфа | Работа | Подработки | Вакансии | Чат', 'rabotaem_ufa'], ['Работа в Уфе', 'ufa_vakansii_podrabotka'], ['Работа Подработка Уфа', 'rabota_ufa_102ru'],
            ['Уфа Объявления', 'ufa_ads1'], ['Барахолка Уфа', 'baraholkaufa'], ['Работа / Объявления / Вакансии Уфа', 'obyavlenya_ufa'], ['УФА ЧАТ', 'ufa_chat02'], ['Уфа. Чат.', 'ufa_topchat'], ['Уфа Объявления / Реклама / Вакансии', 'obyavleni9_ufa'], ['Объявления Уфа | Республика Башкортостан', 'Ufa_obyavlenia'], ['Уфа Объявления', 'obyavleniaufa'], ['ОБЪЯВЛЕНИЯ УФА', 'ufaoobyavleniya'], ['Уфа Услуги / Реклама / Объявления', 'ufauslugireklama'], ['Уфа Барахолка / Объявления', 'ObiyavlenieUfa'], ['Объявления Уфа. Башкортостан', 'novoctiufa'], ['БАРАХОЛКА | УФА', 'ufa_baraholka'], ['Барахолка Уфа Товары и Услуги', 'baraholka_Ufa_uslugi'], ['Уфа чат объявления', 'ufa_chat_ads'], ['Уфа товары и услуги', 'doska_obyavleniy_ufa'],
            ['Чат Дёма', 'demaufa'], ['Микрорайон Зелёная Роща Уфа', 'zelenkaufa'], ['Город Уфа', 'ufacityinfochat'], ['УФА №1 | Полезный чат', 'ufa1chat'], ['Новая Дёма Уфа', 'novaya_dema_zhk_ufa'], ['Нагаево.Инфо', 'nagaevo_info2'],
            ['Мамочки Уфа', 'mamochka_ufa'], ['МАМЧАТ УФА', 'ufamamy'], ['МАМЫ УФЫ', 'mamyufa'],
        ];
        foreach ($channels as [$name, $username]) {
            TelegramChannel::query()->updateOrCreate(['url' => 'https://t.me/'.$username], ['name' => $name, 'username' => $username, 'active' => true]);
        }
    }
}
