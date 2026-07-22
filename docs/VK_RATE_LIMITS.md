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

## Captcha and blocks (strategy)

1. **Detect**  
   - Parser returns HTTP 4xx/5xx or `{ success: false, error: "..." }` → `ParserClient` throws; scan run ends as `failed` / job retries.  
   - Empty post lists can be legitimate (empty wall) or a soft block — check logs and the group in a real browser.

2. **Backoff**  
   - Job-level exponential backoff already applies.  
   - If captcha/blocks persist: increase `VK_SCAN_GROUP_DELAY_SECONDS` (e.g. 90–180), lower `VK_SCAN_LIMIT`, disable comments temporarily (`VK_SCAN_WITH_COMMENTS=false`).

3. **Do not**  
   - Run many parallel Chromium scrapes against VK.  
   - Bypass captchas with third-party solvers in production without legal review.  
   - Hammer the same group every minute.

4. **Recovery**  
   - Pause the group (`active = false`) in admin.  
   - Restart parser container if Chromium is wedged.  
   - Inspect **Scan Runs** and failed queue jobs; Telegram alerts fire on permanent job failure (if notify enabled).

## Legal / product notes

- Scraping public pages may still violate VK ToS; use for **internal lead research** at your own risk.
- Prefer commercial keywords and active groups you care about — less noise, less traffic.
- Store only fields you need (text, urls, ids); do not scrape private content or personal data beyond public posts/comments.

## Post time window (comments + lead match)

Parser always returns the **top N** wall posts (`VK_SCAN_LIMIT`). After upsert, only posts inside the window get **comments scrape** and **keyword match**:

| `VK_SCAN_POST_WINDOW` | Meaning |
|-----------------------|---------|
| `since_last_scan` (default) | `posted_at >= last_scan_at`; first scan uses **start of today** |
| `today` | calendar day in app timezone |
| `all` | no date filter (every post in the N-slice) |

**Important:** this does **not** download “all posts for the day” from VK. If a group publishes more than N posts between scans, raise `VK_SCAN_LIMIT` (e.g. 10–15) or scan more often. Full historical rematch: `php artisan vk:match-leads`.

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
