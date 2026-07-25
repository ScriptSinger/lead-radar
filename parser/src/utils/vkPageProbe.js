/**
 * Multi-signal VK page classifier for captcha / login / block detection.
 *
 * Pure core: classifyPageSnapshot(snapshot) — unit-testable without browser.
 * Browser helper: probePage(page) — collects DOM snapshot then classifies.
 */

const logger = require("./logger");

/** @typedef {'ok'|'captcha'|'login'|'blocked'|'empty_wall'|'unknown'} PageVerdict */

/**
 * @typedef {object} PageSignal
 * @property {string} id
 * @property {number} weight  positive contribution to that bucket
 * @property {string} detail
 * @property {'captcha'|'login'|'blocked'|'empty'|'ok'} bucket
 */

/**
 * @typedef {object} PageSnapshot
 * @property {string} url
 * @property {string} title
 * @property {string} bodyText
 * @property {string} bodyHtmlSample
 * @property {Record<string, number>} counts
 * @property {boolean} hasPasswordInput
 * @property {boolean} hasLoginButton
 * @property {number} bodyTextLen
 */

/**
 * @typedef {object} ProbeResult
 * @property {PageVerdict} verdict
 * @property {number} confidence  0–100
 * @property {PageSignal[]} signals
 * @property {PageSnapshot} page
 * @property {Record<string, number>} scores
 * @property {boolean} isBlocking  captcha|login|blocked
 */

const POST_SELECTORS = {
    post_testid: '[data-testid="post"]',
    post_class: ".post._post, div._post.post, .post",
    wall_item: ".wall_item",
    wall_reply: ".ReplyItem, div[id^='wall_reply']",
};

/**
 * Collect a lightweight DOM snapshot (runs in browser via page.evaluate).
 * @param {import('playwright').Page} page
 * @returns {Promise<PageSnapshot>}
 */
async function collectPageSnapshot(page) {
    return page.evaluate(() => {
        const text = (document.body?.innerText || "").replace(/\s+/g, " ").trim();
        const html = (document.body?.innerHTML || "").slice(0, 4000);

        const q = (sel) => {
            try {
                return document.querySelectorAll(sel).length;
            } catch {
                return 0;
            }
        };

        return {
            url: location.href || "",
            title: document.title || "",
            bodyText: text.slice(0, 1200),
            bodyHtmlSample: html,
            bodyTextLen: text.length,
            counts: {
                post_testid: q('[data-testid="post"]'),
                post_class: q(".post._post, div._post.post, .post"),
                wall_item: q(".wall_item"),
                wall_reply: q(".ReplyItem, div[id^='wall_reply']"),
                captcha_node: q(
                    "[class*='captcha'], [id*='captcha'], iframe[src*='captcha'], .NotRobot, [class*='NotRobot']",
                ),
                challenge_node: q(
                    "[class*='challenge'], [id*='challenge'], .page_block_header",
                ),
            },
            hasPasswordInput: Boolean(
                document.querySelector('input[type="password"]'),
            ),
            hasLoginButton: Boolean(
                document.querySelector(
                    'button[type="submit"], button, a, input[type="submit"]',
                ) &&
                    /войти|login|sign in/i.test(
                        document.body?.innerText || "",
                    ),
            ),
        };
    });
}

/**
 * Classify a page snapshot into captcha / login / blocked / ok / empty.
 * Multi-signal weighted scoring — no single fragile check.
 *
 * @param {PageSnapshot} snap
 * @param {{ expect?: 'wall'|'comments' }} [opts]
 * @returns {ProbeResult}
 */
function classifyPageSnapshot(snap, opts = {}) {
    const expect = opts.expect || "wall";
    /** @type {PageSignal[]} */
    const signals = [];

    const url = String(snap.url || "");
    const title = String(snap.title || "");
    const body = String(snap.bodyText || "");
    const html = String(snap.bodyHtmlSample || "");
    const blob = `${url}\n${title}\n${body}\n${html}`.toLowerCase();

    const postCount =
        (snap.counts?.post_testid || 0) +
        (snap.counts?.post_class || 0) +
        (snap.counts?.wall_item || 0);
    const replyCount = snap.counts?.wall_reply || 0;
    // Comment pages always show the parent post first; replies load later.
    // Treat wall post nodes as "content present" so we don't false-flag login.
    const wallContentCount = postCount + replyCount;
    const contentCount =
        expect === "comments" ? wallContentCount : postCount;

    // --- captcha / bot challenge (hard signals) ---
    if (/challenge\.html/i.test(url) || /[?&]hash429=/i.test(url)) {
        signals.push({
            id: "url_challenge",
            weight: 55,
            detail: `url=${url.slice(0, 160)}`,
            bucket: "captcha",
        });
    }
    if (/проверяем[\s\S]{0,40}не\s*робот/i.test(body + title)) {
        signals.push({
            id: "text_not_robot_ru",
            weight: 50,
            detail: "body/title: «Проверяем… не робот»",
            bucket: "captcha",
        });
    }
    if (/not a robot|are you a robot|security check/i.test(blob)) {
        signals.push({
            id: "text_not_robot_en",
            weight: 45,
            detail: "EN robot/security check text",
            bucket: "captcha",
        });
    }
    if ((snap.counts?.captcha_node || 0) > 0) {
        signals.push({
            id: "captcha_marker",
            weight: 40,
            detail: `captcha in page (nodes=${snap.counts?.captcha_node || 0})`,
            bucket: "captcha",
        });
    }
    // Only short interstitial pages — not normal chrome with «Продолжить»
    if (
        /продолжить/i.test(body) &&
        (snap.bodyTextLen || 0) < 250 &&
        wallContentCount === 0 &&
        /challenge|captcha|робот/i.test(blob)
    ) {
        signals.push({
            id: "short_continue_interstitial",
            weight: 25,
            detail: "short challenge-like page with «Продолжить»",
            bucket: "captcha",
        });
    }

    // --- login wall (must be strong: chrome always has «Войти») ---
    const strongLoginUrl =
        /login\.vk\.|act=login|oauth\.vk|\/login/i.test(url);
    if (strongLoginUrl) {
        signals.push({
            id: "url_login",
            weight: 55,
            detail: "login URL",
            bucket: "login",
        });
    }
    if (snap.hasPasswordInput && wallContentCount === 0) {
        signals.push({
            id: "password_input_no_content",
            weight: 45,
            detail: "password field without wall content",
            bucket: "login",
        });
    }
    // Require multi-word login gates — single «Войти» is always in VK header
    if (
        wallContentCount === 0 &&
        /чтобы\s+продолжить[^.!?]{0,40}вой(ти|дите)|необходимо\s+войти|войдите\s+в\s+аккаунт|войдите\s*,\s*чтобы/i.test(
            body,
        )
    ) {
        signals.push({
            id: "text_must_login",
            weight: 45,
            detail: "strong login-required copy",
            bucket: "login",
        });
    }

    // --- blocked / denied ---
    if (
        wallContentCount === 0 &&
        /access denied|доступ запрещён|страница удалена|page not found|ошибка доступа/i.test(
            blob,
        )
    ) {
        signals.push({
            id: "access_denied",
            weight: 45,
            detail: "access denied / deleted page text",
            bucket: "blocked",
        });
    }
    if (
        wallContentCount === 0 &&
        /429|too many requests|слишком много запросов/i.test(blob)
    ) {
        signals.push({
            id: "rate_limit_page",
            weight: 40,
            detail: "rate-limit style page",
            bucket: "blocked",
        });
    }

    // --- empty / soft-empty (never blocking by itself) ---
    if (contentCount === 0 && (snap.bodyTextLen || 0) < 200) {
        signals.push({
            id: "no_content_short_body",
            weight: 20,
            detail: `no posts/replies, bodyLen=${snap.bodyTextLen || 0}`,
            bucket: "empty",
        });
    }
    if (contentCount === 0) {
        signals.push({
            id: "zero_content_nodes",
            weight: 10,
            detail: `postCount=${postCount} replyCount=${replyCount} expect=${expect}`,
            bucket: "empty",
        });
    }

    // --- healthy page content ---
    if (contentCount > 0) {
        signals.push({
            id: "has_content_nodes",
            weight: 60,
            detail: `contentCount=${contentCount} (posts=${postCount} replies=${replyCount})`,
            bucket: "ok",
        });
    }

    const scores = { captcha: 0, login: 0, blocked: 0, empty: 0, ok: 0 };
    for (const s of signals) {
        scores[s.bucket] = (scores[s.bucket] || 0) + s.weight;
    }

    /** @type {PageVerdict} */
    let verdict = "unknown";
    let confidence = 0;

    const blocking = [
        ["captcha", scores.captcha],
        ["login", scores.login],
        ["blocked", scores.blocked],
    ].sort((a, b) => b[1] - a[1]);

    const [topBlock, topBlockScore] = blocking[0];

    // Hard rule: wall/post content present and no strong captcha URL → ok
    // (fixes m.vk comment pages: header has «Войти», post visible, replies not yet)
    if (scores.ok >= 50 && topBlockScore < 55) {
        verdict = "ok";
        confidence = Math.min(100, scores.ok);
    } else if (topBlockScore >= 50) {
        // Stricter threshold (was 40) to avoid chrome false positives
        verdict = /** @type {PageVerdict} */ (topBlock);
        confidence = Math.min(
            100,
            topBlockScore - Math.floor(scores.ok / 2),
        );
        confidence = Math.max(50, confidence);
    } else if (scores.empty >= 20 && scores.ok === 0) {
        // Ambiguous empty — not blocking
        verdict = "empty_wall";
        confidence = Math.min(70, scores.empty + 10);
    } else {
        verdict = scores.ok > 0 ? "ok" : "unknown";
        confidence = scores.ok > 0 ? Math.min(80, scores.ok) : 15;
    }

    const isBlocking =
        verdict === "captcha" || verdict === "login" || verdict === "blocked";

    return {
        verdict,
        confidence,
        signals,
        page: snap,
        scores,
        isBlocking,
    };
}

/**
 * @param {import('playwright').Page} page
 * @param {{ expect?: 'wall'|'comments', log?: boolean, context?: object }} [opts]
 * @returns {Promise<ProbeResult>}
 */
async function probePage(page, opts = {}) {
    const snap = await collectPageSnapshot(page);
    const result = classifyPageSnapshot(snap, { expect: opts.expect });

    if (opts.log !== false) {
        const level =
            result.verdict === "ok"
                ? "info"
                : result.isBlocking
                  ? "error"
                  : "warn";

        logger[level]("vk.page_probe", {
            ...opts.context,
            verdict: result.verdict,
            confidence: result.confidence,
            is_blocking: result.isBlocking,
            scores: result.scores,
            signals: result.signals.map((s) => ({
                id: s.id,
                bucket: s.bucket,
                weight: s.weight,
                detail: s.detail,
            })),
            page: {
                url: snap.url,
                title: snap.title,
                body_len: snap.bodyTextLen,
                body_snippet: snap.bodyText.slice(0, 180),
                counts: snap.counts,
                has_password: snap.hasPasswordInput,
            },
        });
    }

    return result;
}

/**
 * Map verdict → ScrapeError code.
 * @param {PageVerdict} verdict
 */
function codeForVerdict(verdict) {
    switch (verdict) {
        case "captcha":
            return "VK_CAPTCHA";
        case "login":
            return "VK_LOGIN";
        case "blocked":
            return "VK_BLOCKED";
        case "empty_wall":
            return "EMPTY_WALL";
        default:
            return "PARSE_ERROR";
    }
}

module.exports = {
    POST_SELECTORS,
    collectPageSnapshot,
    classifyPageSnapshot,
    probePage,
    codeForVerdict,
};
