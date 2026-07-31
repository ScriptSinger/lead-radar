# Lead Radar

Сервис поиска лидов в публичных сообществах VK: VK API → хранение → поиск по
ключевым словам → админка → Telegram-уведомления.

Стек: Laravel 13, MoonShine 4, MySQL, database queue и Docker Compose.

## Архитектура

```
scheduler → queue workers → GroupScanner → VK API
                            ├→ MySQL (posts, comments, leads, scan runs)
                            └→ Telegram
```

`VkApiContentSource` использует только официальный VK API; сбор данных через
HTML в проекте не используется.

## Быстрый старт

```bash
cp .env.example .env
# задайте DB_*, APP_KEY и VK_API_TOKEN
docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
```

Сервисы: `php`, `nginx` (:80), `mysql`, `redis`, `worker-scan`, `worker-ops`,
`scheduler` и опционально `ngrok` (:4040).

## Конфигурация VK API

Создайте сервисный ключ в кабинете VK и сохраните его только на сервере:

```env
VK_API_TOKEN=ваш_сервисный_ключ
VK_API_VERSION=5.199
```

Проверка ключа: `GET /api/vk/health`. После изменения `.env` перезапустите
worker-контейнеры. Посты и комментарии будут получаться через VK API.

Остальные важные переменные:

| Переменная | Назначение |
| --- | --- |
| `DB_*` | MySQL |
| `QUEUE_CONNECTION` | Очередь (`database` в Compose) |
| `VK_API_TOKEN` | Сервисный ключ VK API |
| `VK_API_VERSION` | Версия VK API |
| `VK_SCAN_*` | Значения первого запуска; рабочие настройки — в Scan Settings |
| `TELEGRAM_*` | Уведомления и webhook Telegram |

## Сканирование

```bash
php artisan vk:scan
php artisan vk:scan --group=1 --limit=6 --with-comments
php artisan vk:scan --queue
php artisan vk:dispatch-scans
php artisan vk:match-leads
```

В MoonShine → **Scan Settings** задаются интервал, задержка между группами,
лимит постов, загрузка комментариев и окно поиска. По умолчанию расписание
выключено; включите его после проверки `VK_API_TOKEN`.

Telegram: `/scan` показывает состояние и позволяет включить или остановить
расписание, `/stats` — счётчики, `/new` — последние лиды.

## Тесты

```bash
docker compose exec php php artisan test
```

## Структура

```
app/Modules/VkApi/  официальный клиент и источник VK API
app/Services/Vk/    сканирование, сопоставление лидов, дерево комментариев
app/Jobs/           очередь сканирования и уведомлений
```
