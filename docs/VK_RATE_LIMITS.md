# VK scraping: rate limits, captcha, risks

Lead Radar scrapes **public** VK pages via a Playwright microservice (not the official VK API). VK may throttle, show captchas, or block anonymous/automated traffic. This document describes the built-in mitigations and operational guidelines.

## What we already do

| Layer | Control | Default | Config / env |
|-------|---------|---------|--------------|
| Scheduler | One fan-out job per hour, `withoutOverlapping(55)` | hourly | `routes/console.php`, `VK_SCAN_SCHEDULE` (docs) |
| Fan-out | Stagger `ScanVkGroupJob` per group | **45s** between groups | `VK_SCAN_GROUP_DELAY_SECONDS` |
| Volume | Max posts per group scan | **6** (cap 30) | `VK_SCAN_LIMIT` |
| Parser | Serialize navigations + gap between page loads | **1500 ms** | `PARSER_REQUEST_GAP_MS` (parser service) |
| Parser | Navigation timeout / post-load wait | 45s / 4s | `PARSER_NAV_TIMEOUT_MS`, `PARSER_PAGE_WAIT_MS` |
| Jobs | Retries + backoff for flaky parser | 3 tries, 30/90/180s | `ScanVkGroupJob` |
| Jobs | Pre-check `/health`; release 60s if parser down | — | `ScanVkGroupJob` |
| Admin | Manual “Scan now” still goes through the same queue | — | MoonShine VK Groups |

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

## Suggested production baseline

```env
VK_SCAN_LIMIT=6
VK_SCAN_WITH_COMMENTS=true
VK_SCAN_GROUP_DELAY_SECONDS=60
PARSER_REQUEST_GAP_MS=2000
PARSER_TIMEOUT=180
```

With 10 active groups and delay 60s, a full scheduled wave takes ~10 minutes of staggered jobs — acceptable for hourly schedule.

## Observability

- Table `scan_runs` + MoonShine **Scan Runs**
- Dashboard metric **Failed scans 24h**
- Logs: `vk.scan.*`, `vk.scan.job.*`, `queue.job_failed`
- Telegram: permanent `ScanVkGroupJob` failure + other dead-letter jobs
