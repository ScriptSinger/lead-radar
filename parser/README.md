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

## Health

```http
GET /health
```

```json
{ "status": "ok", "service": "parser", "ts": "..." }
```

## API contract

All scrape endpoints return:

- **success** `200`: `{ "success": true, "data": [...] }`
- **error** `4xx/5xx`: `{ "success": false, "error": "message" }`

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

## Env

| Variable                 | Default   | Description                    |
|--------------------------|-----------|--------------------------------|
| `PORT`                   | `3000`    | HTTP port                      |
| `PARSER_NAV_TIMEOUT_MS`  | `45000`   | Navigation timeout             |
| `PARSER_PAGE_WAIT_MS`    | `4000`    | Wait after DOM load            |
| `PARSER_REQUEST_GAP_MS`  | `1500`    | Min gap between navigations    |
| `PARSER_USER_AGENT`      | Chrome UA | Override user-agent            |

## Manual test

```bash
node test-scrape.js
# or
node test-scrape.js https://vk.com/v_inorse 6
```
