# VK Parser

Node.js + Playwright microservice that scrapes public VK group walls and post comments.

## Run

```bash
# local
npm install
node src/index.js

# docker compose (from project root)
docker compose up parser
```

Default port: `3000`.

## Access control

Every endpoint requires `Authorization: Bearer $PARSER_SERVICE_TOKEN`.
Set the same non-empty `PARSER_SERVICE_TOKEN` in Laravel and parser. In the
production Compose file the port is not published; the development override
binds it only to `127.0.0.1`.

## Health

```http
GET /health
GET /auth/status
```

```json
{
  "status": "ok",
  "service": "parser",
  "auth": {
    "session_loaded": true,
    "cookie_count": 12,
    "login_env_set": true
  }
}
```

## VK login (optional, for comments)

Anonymous m.vk often shows *«Войдите в аккаунт…»* promo and may hide replies.  
Logged-in **Playwright storageState** improves comment visibility.

```bash
# in project .env
VK_LOGIN=phone_or_email
VK_PASSWORD=secret

docker compose up -d parser
docker compose exec parser node src/auth/login.js
# → parser/data/vk-storage-state.json

curl -s http://localhost:3000/health \
  -H "Authorization: Bearer $PARSER_SERVICE_TOKEN" | jq .auth
```

Re-login when session dies or captcha blocks scrapes.  
**Do not commit** `vk-storage-state.json`. Using a personal account may violate VK ToS.

## API contract

All scrape endpoints return:

- **success** `200`: `{ "success": true, "data": [...] }`
- **error** `4xx/5xx`: `{ "success": false, "error": "message", "code"?: "VK_CAPTCHA"|"VK_LOGIN"|"VK_BLOCKED"|"EMPTY_WALL"|"PARSE_ERROR", "diagnostics"?: object }`
  - Captcha / login / block → **HTTP 423** + structured `diagnostics` (verdict, confidence, signals, page snippet)
  - Logs: JSON line `vk.page_probe` on every scrape navigation

### `POST /scrape/group`

Scrape recent posts from a group/public page.

**Body**

| Field   | Type   | Required | Description                          |
|---------|--------|----------|--------------------------------------|
| `url`   | string | yes      | `https://vk.com/...` or `vk.ru`      |
| `limit` | number | no       | 1–30, default `6`                    |

**Post item**

| Field           | Type           | Description                                      |
|-----------------|----------------|--------------------------------------------------|
| `vk_post_id`    | string         | e.g. `"-123456_789"`                             |
| `text`          | string         | Post text (may be empty)                         |
| `url`           | string         | Canonical `https://vk.com/wall...` link          |
| `posted_at`     | string\|null   | ISO-8601 when parseable                          |
| `author_id`     | number\|null   | VK user/group id when found                      |
| `posted_at_raw` | string\|null   | Original date string from the page               |

**Example**

```bash
curl -s -X POST http://localhost:3000/scrape/group \
  -H "Authorization: Bearer $PARSER_SERVICE_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://vk.com/halturaufa","limit":6}'
```

### `POST /scrape/comments`

Scrape comments for a wall post URL.

**Body**

| Field | Type   | Required | Description                |
|-------|--------|----------|----------------------------|
| `url` | string | yes      | Wall post URL on vk.com    |

**Comment item**

| Field               | Type           | Description                          |
|---------------------|----------------|--------------------------------------|
| `vk_comment_id`     | string         | Comment id                           |
| `vk_post_id`        | string\|null   | Parent post id from URL when known   |
| `parent_comment_id` | string\|null   | Thread parent if present             |
| `text`              | string         | Comment text                         |
| `url`               | string         | Link with `?reply=` when possible    |
| `posted_at`         | string\|null   | ISO-8601 when parseable              |
| `author_id`         | number\|null   | Author id when found                 |
| `posted_at_raw`     | string\|null   | Original date string                 |

## Behaviour (Phase 1)

- Single long-lived Chromium process; new context per request
- Concurrent scrapes are queued; gap between navigations
- Desktop Chrome user-agent + `ru-RU` locale
- Up to 2 retries on timeout / network errors
- Structured JSON logs to stdout
- Comments are scraped via **m.vk.com** (desktop often hides replies for anonymous sessions); empty `data` is a valid response when the post has no public comments
- Nested replies: expands collapsed controls; follows m.vk `offset=` pagination (`RepliesThreadNext__link` / «Показать все комментарии»); parent from `RepliesThread`, `?thread=`, offset `?reply=ROOT`, and DOM nesting

## Env

| Variable                 | Default   | Description                    |
|--------------------------|-----------|--------------------------------|
| `PORT`                   | `3000`    | HTTP port                      |
| `PARSER_NAV_TIMEOUT_MS`  | `45000`   | Navigation timeout             |
| `PARSER_PAGE_WAIT_MS`    | `4000`    | Wait after DOM load            |
| `PARSER_REQUEST_GAP_MS`  | `1500`    | Min gap between navigations    |
| `PARSER_USER_AGENT`      | Chrome UA | Override user-agent            |
| `PARSER_COMMENT_EXPAND_ROUNDS` | `12` | Max expand-click rounds for comments |
| `PARSER_COMMENT_EXPAND_WAIT_MS` | `900` | Wait after each expand round |

## Manual test

```bash
node test-scrape.js
# or
node test-scrape.js https://vk.com/v_inorse 6
```
