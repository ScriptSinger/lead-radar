# VK scraping: rate limits, captcha, risks

Lead Radar scrapes **public** VK pages via a Playwright microservice (not the official VK API). VK may throttle, show captchas, or block anonymous/automated traffic. This document describes the built-in mitigations and operational guidelines.

## What we already do

| Layer | Control | Default (seeder) | Where |
|-------|---------|------------------|--------|
| Scheduler | Minute tick → dispatch if interval elapsed | **30 min** | MoonShine **Scan Settings** (`interval_minutes`) |
| Fan-out | Stagger `ScanVkGroupJob` per group | **50 s** | Scan Settings `group_delay_seconds` |
| Volume | Max posts per group | **8** (cap 30) | Scan Settings `scan_limit` |
| Comments | Scrape comments for in-window posts | **on** | Scan Settings `with_comments` |
| Window | Match/comments date filter | `since_last_scan` | Scan Settings `post_window` |
| Parser | Serialize navigations + gap | **1500 ms** | `PARSER_REQUEST_GAP_MS` |
| Jobs | Retries + backoff | 3 tries, 30/90/180s | `ScanVkGroupJob` |
| Admin | Manual “Scan now” | uses same settings limit/comments | MoonShine VK Groups |

**Rule of thumb:** treat the pipeline as **serial-ish**, not a parallel crawl farm. Prefer more groups with longer delays over high concurrency.

## Captcha detection & logging

Parser runs a **multi-signal page probe** after each navigation (`vk.page_probe` JSON log).

| Verdict | Code | Meaning |
|---------|------|---------|
| `captcha` | `VK_CAPTCHA` | Bot challenge (`challenge.html`, «не робот», captcha DOM) |
| `login` | `VK_LOGIN` | Login wall / password form without wall content |
| `blocked` | `VK_BLOCKED` | Access denied / rate-limit page |
| `empty_wall` | `EMPTY_WALL` | No posts, no strong captcha signals |
| `ok` | — | Wall content present |

Each probe log includes:

- `verdict`, `confidence` (0–100), `scores`, `signals[]` (`id`, `weight`, `bucket`, `detail`)
- `page.url`, `page.title`, `page.body_snippet`, `page.counts`

**API error shape** (HTTP **423** for captcha/login/block):

```json
{
  "success": false,
  "error": "VK captcha (confidence=90): …",
  "code": "VK_CAPTCHA",
  "diagnostics": { "verdict": "captcha", "confidence": 90, "signals": [], "page": {} }
}
```

**Laravel logs:**

| Event | When |
|-------|------|
| `vk.page_probe` | Parser (stdout JSON) |
| `vk.parser.request_failed` | ParserClient got non-success |
| `vk.scan.scrape_blocked` | GroupScanner / ScanRun `status=captcha` |
| `vk.scan.job.scrape_blocked` | Job (long release 180s, no thrash retries) |
| `vk.scan.empty_posts` | `[]` without blocking verdict |

Captcha is **not** retried immediately by the parser (`retryable: false`). Job waits ~180s once, then fails permanently → Telegram (if enabled).

## Captcha and blocks (strategy)

1. **Detect** — see table above; do not treat silent `data:[]` as success without reading `vk.page_probe`.  
2. **Backoff** — raise `group_delay_seconds` (90–180), lower limit, disable comments, pause schedule.  
3. **Do not** — parallel Chromium farm, captcha solvers without legal review, 1‑minute hammering.  
4. **Recovery** — `active=false` on group, restart parser, inspect Scan Runs with status `captcha`.

## Logged-in session (optional)

Parser can reuse a Playwright **storageState** (cookies) after:

```bash
# .env: VK_LOGIN=… VK_PASSWORD=…
docker compose exec parser node src/auth/login.js
curl -s localhost:3000/health | jq .auth
```

Helps when m.vk hides comments for anonymous sessions.  
Session file: `parser/data/vk-storage-state.json` (gitignored).  
Re-auth when probes show login walls or empty replies after a working wall scrape.

## Legal / product notes

- Scraping public pages may still violate VK ToS; use for **internal lead research** at your own risk.
- Logging in with a real account increases ToS / ban risk — use a dedicated throwaway if possible.
- Prefer commercial keywords and active groups you care about — less noise, less traffic.
- Store only fields you need (text, urls, ids); do not scrape private content or personal data beyond public posts/comments.

## Post time window vs comments

Parser returns the **top N** wall posts (`scan_limit`).

| | Post body keywords | Comments scrape + comment keywords |
|--|--------------------|-------------------------------------|
| **in window** (`since_last_scan` / `today` / `all`) | yes | yes |
| **outside window** (old post still in top-N) | no | **yes** (new replies under old posts) |

Window modes for **post body** only:

| `post_window` | Meaning |
|---------------|---------|
| `since_last_scan` (default) | `posted_at >= last_scan_at`; first scan → start of today |
| `today` | calendar day in app timezone |
| `all` | match post body for every post in the N-slice |

If a group posts more than N items between scans, raise `scan_limit` or scan more often. Full rematch: `php artisan vk:match-leads`.

For **50 groups every ~5 hours**, prefer:

```env
VK_SCAN_POST_WINDOW=since_last_scan
VK_SCAN_LIMIT=10
VK_SCAN_GROUP_DELAY_SECONDS=100
VK_SCAN_WITH_COMMENTS=false
```

Use `today` if you want a strict “only today’s applications” rule and accept missing late-yesterday posts after midnight.

## Suggested production baseline

```env
VK_SCAN_LIMIT=6
VK_SCAN_WITH_COMMENTS=true
VK_SCAN_GROUP_DELAY_SECONDS=60
VK_SCAN_POST_WINDOW=since_last_scan
PARSER_REQUEST_GAP_MS=2000
PARSER_TIMEOUT=180
```

With 10 active groups and delay 60s, a full scheduled wave takes ~10 minutes of staggered jobs — acceptable for hourly schedule.

## Observability

- Table `scan_runs` + MoonShine **Scan Runs**
- Dashboard metric **Failed scans 24h**
- Logs: `vk.scan.*`, `vk.scan.job.*`, `queue.job_failed`
- Telegram: permanent `ScanVkGroupJob` failure + other dead-letter jobs
