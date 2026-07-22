# Lead Radar

Сервис генерации лидов из публичных групп VK: scrape → persist → keyword match → ops UI → Telegram.

Стек: **Laravel 13** (PHP 8.3+), **MoonShine 4**, **MySQL**, queue (database), **Node + Playwright** parser, Docker Compose.

---

## Архитектура

```
┌─────────────┐     HTTP      ┌──────────────────┐
│  scheduler  │──dispatch──▶  │  worker (queues) │
│ schedule:work│              │  vk.scan, …      │
└─────────────┘              └────────┬─────────┘
                                      │
                              GroupScanner
                                      │
                    ┌─────────────────┼─────────────────┐
                    ▼                 ▼                 ▼
              ParserClient      LeadMatcher        ScanRun
                    │                 │
                    ▼                 ▼
            ┌────────────┐      ┌──────────┐     ┌────────────┐
            │  parser    │      │  MySQL   │     │ Telegram   │
            │ Playwright │      │ posts,   │     │ Bot API    │
            │ :3000      │      │ leads…   │     │ (notify)   │
            └────────────┘      └──────────┘     └────────────┘
                                      ▲
                                      │
                               MoonShine admin
                               (nginx → php-fpm)
```

| Компонент | Роль |
|-----------|------|
| `parser/` | Microservice: wall posts + nested comments (m.vk) |
| `GroupScanner` | Health-check, upsert posts/comments, match leads, `scan_runs` |
| `LeadMatcher` | Substring match (ё→е), `dedupe_key`, score +10 |
| Jobs | `DispatchVkGroupScansJob` → `ScanVkGroupJob`; `NotifyNewLeadJob` |
| MoonShine | Leads, Keywords, VK Groups/Posts/Comments, Scan Runs, Dashboard |

Подробности rate limit / captcha: [docs/VK_RATE_LIMITS.md](docs/VK_RATE_LIMITS.md).  
Контракт parser: [parser/README.md](parser/README.md).

---

## Быстрый старт (Docker)

```bash
cp .env.example .env
# задать DB_*, APP_KEY, при необходимости TELEGRAM_*, NGROK_AUTHTOKEN

docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
# MoonShine user — по доке пакета / seed, если настроен
```

Сервисы: `php`, `nginx` (:80), `mysql`, `redis`, `parser` (:3000), `worker`, `scheduler`, опционально `ngrok` (:4040).

Worker ждёт healthy MySQL и слушает очереди:

`vk.scan`, `telegram.webhook`, `broadcast.telegram`, `default`.

---

## Переменные окружения

| Переменная | Назначение |
|------------|------------|
| `DB_*` | MySQL |
| `QUEUE_CONNECTION` | `database` (рекомендуется в compose) |
| `CACHE_STORE` | `file` — стабильнее для worker, чем cache DB при старте |
| `PARSER_URL` | URL parser, в Docker: `http://parser:3000` |
| `PARSER_TIMEOUT` | Таймаут HTTP к parser (сек), default 180 |
| `VK_SCAN_*` | Только fallback; **боевые** параметры — MoonShine **Scan Settings** |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | Уведомления о лидах |
| `TELEGRAM_NOTIFY_ENABLED` | `true`/`false` |
| `TELEGRAM_WEBHOOK_URL` | Публичный URL webhook (ngrok) |
| `TELEGRAM_WEBHOOK_SECRET` | Опциональный secret header |
| `NGROK_AUTHTOKEN` | Для локального webhook |

Полный шаблон: [`.env.example`](.env.example).

---

## Команды

```bash
# Синхронный скан (нужен живой parser)
php artisan vk:scan
php artisan vk:scan --group=1 --limit=6 --with-comments
php artisan vk:scan --queue                    # через dispatch

# Поставить в очередь сканы активных групп (со stagger)
php artisan vk:dispatch-scans
php artisan vk:dispatch-scans --group=1

# Перематчить лиды по уже сохранённым постам/комментам
php artisan vk:match-leads

# Telegram webhook
php artisan telegram:setup-webhook
php artisan telegram:setup-webhook --info
```

Ручной скан из админки: **VK Groups → Scan now** (async job).

---

## Очереди и расписание

- **Scan Settings** (БД + MoonShine): interval, delay, limit, comments, post window, on/off.
- **Scheduler** (`schedule:work`): каждую минуту tick → если `interval_minutes` прошёл — fan-out.
- Дефолт сидера: **каждые 30 мин**, delay 50s, limit 8, comments on, window `since_last_scan`.
- **Worker**: timeout 300s; parser down → release/backoff; fail → Telegram (если включено).

```bash
php artisan migrate
php artisan db:seed --class=ScanSettingSeeder
```

---

## Админка (MoonShine)

| Раздел | Зачем |
|--------|--------|
| Dashboard | New/processed leads, failed scans 24h, recent runs |
| Leads | Операции: new → processed / ignored, ссылка VK |
| Keywords | Слова; type: post / comment / both; rematch при save |
| VK Groups | URL, active, last_scan, scan now |
| Scan Runs | Read-only observability прогонов |
| Posts / Comments | Сырые данные, дерево комментов |

---

## Matching (v1)

- Нормализация: `mb_strtolower`, `ё→е`, схлопывание пробелов.
- Match: substring (`mb_strpos`).
- Один lead на пару keyword × post или keyword × comment.
- `dedupe_key`: `p:{postId}:k:{keywordId}` / `c:{commentId}:k:{keywordId}` (unique).
- Повторный match **не сбрасывает** `status` (processed/ignored сохраняются).
- При скане comments + match только для постов в **окне** (`VK_SCAN_POST_WINDOW`):
  - `since_last_scan` (default) — с прошлого `last_scan_at`, первый скан = **сегодня**;
  - `today` — календарный день;
  - `all` — все N постов с парсера.
- Парсер по-прежнему отдаёт top-N стены; «весь день» = достаточный `VK_SCAN_LIMIT` + окно.

---

## Тесты

```bash
docker compose exec php php artisan test
# или
docker compose exec php php vendor/bin/phpunit
```

Покрытие фазы 8:

- Unit: нормализация keywords / `VkUrl`
- Feature: LeadMatcher upsert+dedup, GroupScanner + `Http::fake` parser, ParserClient, dispatch rate-limit skip

---

## Риски VK

Парсинг публичных страниц без API — throttling, captcha, empty walls. См. **[docs/VK_RATE_LIMITS.md](docs/VK_RATE_LIMITS.md)**.

---

## Структура (ключевое)

```
app/
  Console/Commands/     vk:scan, vk:dispatch-scans, vk:match-leads, telegram:…
  Jobs/                 ScanVkGroupJob, DispatchVkGroupScansJob, NotifyNewLeadJob, RematchLeadsJob
  Models/               VkGroup, VkPost, VkComment, Keyword, Lead, ScanRun
  Services/Vk/          ParserClient, GroupScanner, LeadMatcher, CommentTreeResolver
  Services/Telegram/    TelegramNotifier
  Support/VkUrl.php
  MoonShine/            resources + Dashboard
parser/                 Node Express + Playwright
docs/VK_RATE_LIMITS.md
```

---

## License

MIT (как типичный Laravel-проект; уточняйте при публикации).
