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
| `parser/` | Legacy microservice: wall posts + nested comments (m.vk) |
| `app/Modules/VkApi/` | Official VK API source: communities, posts and comments |
| `GroupScanner` | Source-agnostic health-check, upsert posts/comments, match leads, `scan_runs` |
| `LeadMatcher` | Substring match (ё→е), `dedupe_key`, score +10 |
| Jobs | `DispatchVkGroupScansJob` → `ScanVkGroupJob`; `NotifyNewLeadJob` |
| MoonShine | Leads, Keywords, VK Groups/Posts/Comments, Scan Runs, Dashboard |

Подробности rate limit / captcha: [docs/VK_RATE_LIMITS.md](docs/VK_RATE_LIMITS.md).  
Контракт parser: [parser/README.md](parser/README.md).

---

## Быстрый старт (Docker)

```bash
cp .env.example .env
# задать DB_*, APP_KEY, PARSER_SERVICE_TOKEN,
# при необходимости TELEGRAM_*, NGROK_AUTHTOKEN
# openssl rand -hex 32  # значение для PARSER_SERVICE_TOKEN

docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
# MoonShine user — по доке пакета / seed, если настроен
```

Сервисы: `php`, `nginx` (:80), `mysql`, `redis`, `parser` (:3000), `worker`, `scheduler`, опционально `ngrok` (:4040).

`worker-scan` ждёт healthy MySQL и слушает только тяжёлую очередь `vk.scan`.
Источник контента сам проверяет доступность перед началом скана.
`worker-ops` слушает операционные очереди:

`telegram.webhook`, `broadcast.telegram`, `default`.

---

## Переменные окружения

| Переменная | Назначение |
|------------|------------|
| `DB_*` | MySQL |
| `QUEUE_CONNECTION` | `database` (рекомендуется в compose) |
| `CACHE_STORE` | `file` — стабильнее для worker, чем cache DB при старте |
| `PARSER_URL` | URL parser, в Docker: `http://parser:3000` |
| `PARSER_TIMEOUT` | Таймаут HTTP к parser (сек), default 180 |
| `PARSER_SERVICE_TOKEN` | Обязательный в production bearer-токен между Laravel и parser |
| `VK_CONTENT_SOURCE` | `parser` (устаревший сбор страниц) или `api` (официальный VK API) |
| `VK_API_TOKEN` | Сервисный ключ VK, нужен при `VK_CONTENT_SOURCE=api`; только на сервере |
| `VK_API_VERSION` | Версия VK API, по умолчанию `5.199` |
| `VK_SCAN_*` | Только fallback; **боевые** параметры — MoonShine **Scan Settings** |
| `VK_ADAPTIVE_GROUP_DELAY` | Автоматически повышает задержку следующей волны по среднему времени последних сканов |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | Уведомления о лидах |
| `TELEGRAM_NOTIFY_ENABLED` | `true`/`false` |
| `TELEGRAM_WEBHOOK_URL` | Публичный URL webhook (ngrok) |
| `TELEGRAM_WEBHOOK_SECRET` | Обязательный в production secret header Telegram webhook |
| `NGROK_AUTHTOKEN` | Для локального webhook |

Полный шаблон: [`.env.example`](.env.example).

---

## Команды

```bash
# Синхронный скан (использует источник из VK_CONTENT_SOURCE)
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

### Официальный VK API

Для легального сбора публичных данных создайте сервисный ключ в кабинете VK и
установите в `.env`:

```env
VK_CONTENT_SOURCE=api
VK_API_TOKEN=ваш_сервисный_ключ
```

Проверка ключа: `GET /api/vk/health`. После изменения `.env` перезапустите
очередь/контейнеры, затем обычные команды `vk:scan` и задачи расписания будут
использовать только модуль `app/Modules/VkApi/`; Playwright-парсер в этом режиме
не вызывается.

Чтобы загрузить и проверить комментарии при ручном синхронном запуске, флаг
нужен явно: `php artisan vk:scan --with-comments`. Для запуска через очередь
значение по умолчанию берётся из **Scan Settings → With comments**.

---

## Очереди и расписание

- **Scan Settings** (БД + MoonShine): interval, delay, limit, comments, post window, on/off.
- **Scheduler** (`schedule:work`): каждую минуту tick → если `interval_minutes` прошёл — fan-out.
- Дефолт сидера: расписание **выключено** до ручного включения после VK-логина; после включения — каждые 30 мин, delay 50s, limit 8, comments on, window `since_last_scan`.
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
- Match: substring (`mb_strpos`) by default; per-keyword exact word/phrase mode is available.
- Keyword quality controls: `substring` (default, supports stems) or `whole_word`, optional comma/newline-separated negative words, and score 1–1000.
- Один lead на пару keyword × post или keyword × comment.
- `dedupe_key`: `p:{postId}:k:{keywordId}` / `c:{commentId}:k:{keywordId}` (unique).
- Повторный match **не сбрасывает** `status` (processed/ignored сохраняются).
- Окно (`post_window`) режет только **текст поста** для keyword match:
  - `since_last_scan` / `today` / `all` — как раньше.
- **Комментарии** качаются и матчятся для **всех** top-N постов с стены (старый пост + новые replies с ключевиком → lead).

`scan_runs.stats.timings_ms` хранит время health-check, загрузки постов,
комментариев и матчей. Используйте эти метрики, чтобы увеличивать задержку
между группами и отключать комментарии при росте времени ответов VK.

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
  Contracts/            VkContentSource (общий контракт источника)
  Modules/VkApi/        официальный VK API: client + content source
  Services/Vk/          ParserClient, ParserContentSource, GroupScanner, LeadMatcher, CommentTreeResolver
  Services/Telegram/    TelegramNotifier
  Support/VkUrl.php
  MoonShine/            resources + Dashboard
parser/                 Node Express + Playwright
docs/VK_RATE_LIMITS.md
```

---

## License

MIT (как типичный Laravel-проект; уточняйте при публикации).
