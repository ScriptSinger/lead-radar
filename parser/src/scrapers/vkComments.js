const { withPage, goto } = require("../browser/playwright");
const { normalizeComment } = require("../utils/vk");
const logger = require("../utils/logger");

/** Max expand-click rounds per post (show more / show replies). */
const MAX_EXPAND_ROUNDS = Number(process.env.PARSER_COMMENT_EXPAND_ROUNDS || 8);
/** Max offset pages to follow for thread pagination. */
const MAX_OFFSET_PAGES = Number(process.env.PARSER_COMMENT_OFFSET_PAGES || 15);
/** Pause after each expand click (ms). */
const EXPAND_WAIT_MS = Number(process.env.PARSER_COMMENT_EXPAND_WAIT_MS || 800);

/**
 * Scrape comments for a VK wall post.
 *
 * Prefer m.vk.com — desktop often hides replies for anonymous sessions.
 * Expands collapsed threads, follows offset pagination links, merges results.
 *
 * Contract item:
 * {
 *   vk_comment_id: string,
 *   vk_post_id: string|null,
 *   parent_comment_id: string|null,
 *   text: string,
 *   url: string,
 *   posted_at: string|null,
 *   author_id: number|null,
 *   posted_at_raw: string|null
 * }
 *
 * @param {{ url: string }} params
 * @returns {Promise<object[]>}
 */
async function scrapeComments({ url }) {
    logger.info("scrapeComments start", { url });

    const postIdFromUrl = extractPostIdFromUrl(url);
    const mobileUrl = toMobileVkUrl(url);

    const rawComments = await withPage(
        async (page, { waitMs }) => {
            await goto(page, mobileUrl, { waitMs: Math.max(waitMs, 4000) });

            // Collect comments from main page + paginated offset URLs
            const all = [];
            const seenPages = new Set();

            const harvest = async (pageUrl, label) => {
                const key = normalizePageKey(pageUrl);
                if (seenPages.has(key)) {
                    return [];
                }
                seenPages.add(key);

                if (page.url() !== pageUrl && !page.url().includes(key)) {
                    await goto(page, pageUrl, {
                        waitMs: Math.max(waitMs, 3000),
                    });
                }

                const expandStats = await expandAllComments(page);
                const batch = await page.evaluate(
                    extractMobileComments,
                    postIdFromUrl,
                );

                logger.info("scrapeComments harvest", {
                    label,
                    pageUrl,
                    count: batch.length,
                    ...expandStats,
                });

                return batch;
            };

            // 1) Main wall page
            all.push(...(await harvest(mobileUrl, "main")));

            // 2) Discover and follow offset/thread pagination links
            let offsetPages = await collectPaginationUrls(page, mobileUrl);
            let pageGuard = 0;

            while (
                offsetPages.length > 0 &&
                pageGuard < MAX_OFFSET_PAGES
            ) {
                const nextUrl = offsetPages.shift();
                pageGuard++;

                const batch = await harvest(nextUrl, `offset-${pageGuard}`);
                all.push(...batch);

                // New pagination links may appear after loading offset page
                const more = await collectPaginationUrls(page, mobileUrl);
                for (const u of more) {
                    const k = normalizePageKey(u);
                    if (!seenPages.has(k) && !offsetPages.includes(u)) {
                        offsetPages.push(u);
                    }
                }
            }

            // Deduplicate by vk_comment_id (keep first with parent if possible)
            const byId = new Map();
            for (const c of all) {
                const id = String(c.vk_comment_id || "");
                if (!id) continue;
                const prev = byId.get(id);
                if (!prev) {
                    byId.set(id, c);
                    continue;
                }
                // Prefer entry that has parent_comment_id
                if (!prev.parent_comment_id && c.parent_comment_id) {
                    byId.set(id, c);
                }
            }

            let comments = Array.from(byId.values());

            if (!comments.length) {
                const desktopUrl = toDesktopVkUrl(url);
                logger.info("scrapeComments mobile empty, try desktop", {
                    desktopUrl,
                });
                await goto(page, desktopUrl, {
                    waitMs: Math.max(waitMs, 4000),
                });
                await expandAllComments(page);
                comments = await page.evaluate(
                    extractDesktopComments,
                    postIdFromUrl,
                );
            }

            return comments;
        },
        { label: "scrapeComments" },
    );

    const data = (rawComments || [])
        .map((c) => normalizeComment(c, postIdFromUrl))
        .filter((c) => c.vk_comment_id && c.text);

    // Infer parent from URL when DOM missed RepliesThread
    for (const item of data) {
        if (!item.parent_comment_id && item.url) {
            const fromUrl = parentFromUrl(item.url);
            if (fromUrl && fromUrl !== item.vk_comment_id) {
                item.parent_comment_id = fromUrl;
            }
        }
    }

    // If comment was loaded via ?reply=ROOT&offset=… and is not ROOT, parent=ROOT
    // (handled in extract when page has reply= in location)

    const withParent = data.filter((c) => c.parent_comment_id).length;
    const roots = data.length - withParent;

    logger.info("scrapeComments done", {
        url,
        count: data.length,
        roots,
        nested: withParent,
    });

    return data;
}

/**
 * Collect pagination / "show all comments" URLs from current DOM.
 *
 * m.vk uses e.g. /wall-123_456?offset=1&reply=789#789 (RepliesThreadNext__link)
 *
 * @param {import('playwright').Page} page
 * @param {string} baseUrl
 * @returns {Promise<string[]>}
 */
async function collectPaginationUrls(page, baseUrl) {
    const hrefs = await page.evaluate(() => {
        const out = [];
        const nodes = document.querySelectorAll(
            "a.RepliesThreadNext__link, a[href*='offset='], a[href*='Offset=']",
        );
        for (const a of nodes) {
            const href = a.getAttribute("href");
            if (href) out.push(href);
        }
        // Also "Показать все комментарии" even if class differs
        document.querySelectorAll("a").forEach((a) => {
            const t = (a.innerText || "").replace(/\s+/g, " ").trim();
            if (/показать все комментарии/i.test(t)) {
                const href = a.getAttribute("href");
                if (href) out.push(href);
            }
        });
        return out;
    });

    const absolute = [];
    const seen = new Set();

    for (const href of hrefs) {
        try {
            const abs = new URL(href, baseUrl).toString();
            // Only follow comment-related pagination
            if (!/[?&]offset=/i.test(abs) && !/[?&]reply=/i.test(abs)) {
                continue;
            }
            const key = normalizePageKey(abs);
            if (seen.has(key)) continue;
            seen.add(key);
            absolute.push(abs);
        } catch {
            // skip bad href
        }
    }

    return absolute;
}

/**
 * @param {string} url
 */
function normalizePageKey(url) {
    try {
        const u = new URL(url);
        // ignore hash
        u.hash = "";
        return u.toString();
    } catch {
        return String(url).split("#")[0];
    }
}

/**
 * Aggressively expand collapsed comments / thread replies on the page.
 *
 * @param {import('playwright').Page} page
 * @returns {Promise<{ rounds: number, clicks: number, itemsBefore: number, itemsAfter: number }>}
 */
async function expandAllComments(page) {
    const itemsBefore = await countReplyItems(page);
    let clicks = 0;
    let rounds = 0;
    let stagnant = 0;

    for (let round = 0; round < MAX_EXPAND_ROUNDS; round++) {
        rounds++;
        const before = await countReplyItems(page);
        const clickedThisRound = await clickExpandControls(page);
        clicks += clickedThisRound;

        await page
            .evaluate(() => {
                const wrap =
                    document.querySelector(".RepliesWrap") ||
                    document.querySelector(".wall_replies") ||
                    document.querySelector("#replies") ||
                    document.body;
                wrap.scrollTop = wrap.scrollHeight;
                window.scrollBy(0, 600);
            })
            .catch(() => {});

        await page.waitForTimeout(EXPAND_WAIT_MS);

        const after = await countReplyItems(page);

        if (clickedThisRound === 0 && after <= before) {
            stagnant++;
            if (stagnant >= 2) break;
        } else {
            stagnant = 0;
        }
    }

    await page.waitForTimeout(400);
    const itemsAfter = await countReplyItems(page);

    return { rounds, clicks, itemsBefore, itemsAfter };
}

/**
 * @param {import('playwright').Page} page
 * @returns {Promise<number>}
 */
async function clickExpandControls(page) {
    const patterns = [
        "a.RepliesThreadNext__link",
        'a:has-text("Показать все комментарии")',
        'button:has-text("Показать все комментарии")',
        'a:has-text("Показать комментарии")',
        'a:has-text("Показать предыдущие")',
        'a:has-text("Показать следующие")',
        'a:has-text("Ещё комментарии")',
        'a:has-text("ответ")',
        'button:has-text("ответ")',
        ".replies_next",
        ".js-replies_next_link",
        "a.RepliesShowMore",
        ".RepliesShowMore",
        "[class*='ShowMore']",
        "[class*='replies_next']",
    ];

    let clicks = 0;

    for (const sel of patterns) {
        let loc;
        try {
            loc = page.locator(sel);
        } catch {
            continue;
        }

        const count = await loc.count().catch(() => 0);
        const limit = Math.min(count, 6);

        for (let i = 0; i < limit; i++) {
            const el = loc.nth(i);
            try {
                if (!(await el.isVisible({ timeout: 200 }))) continue;

                const text = ((await el.innerText()) || "")
                    .replace(/\s+/g, " ")
                    .trim();

                if (/^ответить$/i.test(text)) continue;

                // Prefer navigate for offset links (JS click is flaky on m.vk)
                const href = await el.getAttribute("href");
                if (href && /offset=/i.test(href)) {
                    // Leave for collectPaginationUrls / harvest — don't fight SPA
                    continue;
                }

                await el.click({ timeout: 1500, force: true });
                clicks++;
                await page.waitForTimeout(200);
            } catch {
                // ignore
            }
        }
    }

    return clicks;
}

/**
 * @param {import('playwright').Page} page
 */
async function countReplyItems(page) {
    return page
        .evaluate(
            () =>
                document.querySelectorAll(
                    ".ReplyItem, div[id^='wall_reply'], .reply",
                ).length,
        )
        .catch(() => 0);
}

/**
 * Parent from reply URL: ?reply=X&thread=Y → Y is thread root / parent.
 *
 * @param {string} href
 * @returns {string|null}
 */
function parentFromUrl(href) {
    try {
        const u = new URL(href, "https://m.vk.com");
        const thread = u.searchParams.get("thread");
        if (thread && /^\d+$/.test(thread)) {
            return thread;
        }
    } catch {
        // ignore
    }

    const m = String(href).match(/[?&#]thread=(\d+)/);
    return m ? m[1] : null;
}

/**
 * Thread root from current page URL (?reply=ROOT&offset=N).
 * Used when harvesting offset pages of a thread.
 *
 * @param {string} pageUrl
 * @returns {string|null}
 */
function threadRootFromPageUrl(pageUrl) {
    try {
        const u = new URL(pageUrl);
        // On offset pages: ?offset=1&reply=214027 means thread under 214027
        if (u.searchParams.has("offset") && u.searchParams.has("reply")) {
            const r = u.searchParams.get("reply");
            if (r && /^\d+$/.test(r)) return r;
        }
    } catch {
        // ignore
    }
    return null;
}

/**
 * Runs in browser context (m.vk.com).
 * @param {string|null} fallbackPostId
 */
function extractMobileComments(fallbackPostId) {
    const result = [];
    const seen = new Set();

    // Page-level thread hint for offset pagination pages
    let pageThreadRoot = null;
    try {
        const u = new URL(location.href);
        if (u.searchParams.has("offset") && u.searchParams.has("reply")) {
            const r = u.searchParams.get("reply");
            if (r && /^\d+$/.test(r)) pageThreadRoot = r;
        }
    } catch {
        // ignore
    }

    const items = document.querySelectorAll(
        ".ReplyItem, div[id^='wall_reply']",
    );

    for (const item of items) {
        if (
            !item.classList.contains("ReplyItem") &&
            item.closest(".ReplyItem")
        ) {
            continue;
        }

        const bodyEl =
            item.querySelector(".ReplyItem__body") ||
            item.querySelector(".pi_text") ||
            item.querySelector(".wall_reply_text");

        const text = bodyEl?.innerText?.trim() || "";
        if (!text) continue;

        let ownerCommentId = null;
        const idAttr = item.id || "";
        const idMatch = idAttr.match(/wall_reply-?(-?\d+)_(\d+)/i);
        if (idMatch) {
            ownerCommentId = `${idMatch[1]}_${idMatch[2]}`;
        }

        const dateLink =
            item.querySelector("a.item_date") ||
            item.querySelector(".ReplyItem__date a") ||
            item.querySelector('a[href*="reply="]');

        let vkCommentId = null;
        let commentUrl = null;
        let href = "";

        if (dateLink) {
            href = dateLink.getAttribute("href") || "";
            commentUrl = href.startsWith("http")
                ? href
                : href
                  ? `https://m.vk.com${href}`
                  : null;
            const rm = href.match(/[?&]reply=(\d+)/);
            if (rm) vkCommentId = rm[1];
        }

        if (!vkCommentId && ownerCommentId) {
            vkCommentId = ownerCommentId.split("_").pop();
        }

        if (!vkCommentId || seen.has(vkCommentId)) continue;
        seen.add(vkCommentId);

        // --- parent resolution ---
        let parentCommentId = null;

        // 1) Inside RepliesThread{parentId}
        const thread = item.closest("[id^='RepliesThread']");
        if (thread && thread.id) {
            const pm = thread.id.match(/RepliesThread(\d+)/);
            if (pm && pm[1] !== vkCommentId) {
                parentCommentId = pm[1];
            }
        }

        // 2) URL ?thread=
        if (!parentCommentId && href) {
            const tm = href.match(/[?&#]thread=(\d+)/);
            if (tm && tm[1] !== vkCommentId) {
                parentCommentId = tm[1];
            }
        }

        // 3) Nested under another ReplyItem
        if (!parentCommentId) {
            const outer = item.parentElement?.closest?.(
                ".ReplyItem, div[id^='wall_reply']",
            );
            if (outer && outer !== item) {
                const outerId = outer.id || "";
                const om = outerId.match(/wall_reply-?(-?\d+)_(\d+)/i);
                if (om && om[2] !== vkCommentId) {
                    parentCommentId = om[2];
                }
            }
        }

        // 4) data attributes
        if (!parentCommentId) {
            const dataParent =
                item.getAttribute("data-parent-id") ||
                item.getAttribute("data-reply-to") ||
                item.getAttribute("data-thread-id") ||
                null;
            if (dataParent) {
                const raw = String(dataParent);
                parentCommentId = raw.includes("_")
                    ? raw.split("_").pop()
                    : raw;
                if (parentCommentId === vkCommentId) {
                    parentCommentId = null;
                }
            }
        }

        // 5) Offset page of a thread: everything except the root is nested
        if (
            !parentCommentId &&
            pageThreadRoot &&
            pageThreadRoot !== vkCommentId
        ) {
            parentCommentId = pageThreadRoot;
        }

        let authorId = null;
        const authorEl =
            item.querySelector(".ReplyItem__name") ||
            item.querySelector("a.author");
        if (authorEl) {
            const ahref = authorEl.getAttribute("href") || "";
            const am = ahref.match(/(?:id|club|public)(-?\d+)/i);
            if (am) authorId = am[1];
        }

        const postedAtRaw =
            dateLink?.textContent?.trim() ||
            item.querySelector(".ReplyItem__date")?.textContent?.trim() ||
            null;

        result.push({
            vk_comment_id: String(vkCommentId),
            vk_post_id: fallbackPostId,
            parent_comment_id: parentCommentId,
            text,
            url: commentUrl,
            posted_at: postedAtRaw,
            posted_at_raw: postedAtRaw,
            author_id: authorId,
        });
    }

    return result;
}

/**
 * Runs in browser context (desktop vk.com).
 * @param {string|null} fallbackPostId
 */
function extractDesktopComments(fallbackPostId) {
    const result = [];
    const seen = new Set();
    const replyNodes = document.querySelectorAll(
        ".reply, [data-testid='comment']",
    );

    for (const item of replyNodes) {
        const textEl =
            item.querySelector(".reply_text") ||
            item.querySelector("[data-testid='comment-text']");
        const text = textEl?.innerText?.trim() || "";
        if (!text) continue;

        let commentId =
            item.getAttribute("data-post-id") ||
            item.getAttribute("data-id") ||
            null;

        if (!commentId && item.id) {
            const m = String(item.id).match(/(-?\d+_\d+)/);
            if (m) commentId = m[1];
        }

        const replyLink = item.querySelector(
            'a[href*="reply="], a.wd_date, a.reply_link',
        );
        const href = replyLink?.getAttribute("href") || "";
        const rm = href.match(/[?&]reply=(\d+)/);

        let vkCommentId = null;
        if (rm) vkCommentId = rm[1];
        else if (commentId && commentId.includes("_"))
            vkCommentId = commentId.split("_").pop();
        else if (commentId) vkCommentId = commentId;

        if (!vkCommentId || seen.has(vkCommentId)) continue;
        seen.add(vkCommentId);

        let parentCommentId = null;
        const parentAttr =
            item.getAttribute("data-parent") ||
            item.getAttribute("data-reply-to") ||
            null;
        if (parentAttr) {
            parentCommentId = String(parentAttr).includes("_")
                ? String(parentAttr).split("_").pop()
                : String(parentAttr);
        }
        if (!parentCommentId && href) {
            const tm = href.match(/[?&#]thread=(\d+)/);
            if (tm && tm[1] !== vkCommentId) parentCommentId = tm[1];
        }

        let authorId = null;
        const authorEl =
            item.querySelector("a.author") ||
            item.querySelector("[data-testid='comment_author']");
        if (authorEl) {
            const ahref = authorEl.getAttribute("href") || "";
            const am = ahref.match(/(?:id|club|public)(-?\d+)/i);
            if (am) authorId = am[1];
        }

        const postedAtRaw = replyLink?.textContent?.trim() || null;

        result.push({
            vk_comment_id: String(vkCommentId),
            vk_post_id: fallbackPostId,
            parent_comment_id: parentCommentId,
            text,
            url: href
                ? href.startsWith("http")
                    ? href
                    : `https://vk.com${href}`
                : null,
            posted_at: postedAtRaw,
            posted_at_raw: postedAtRaw,
            author_id: authorId,
        });
    }

    return result;
}

/**
 * @param {string} url
 * @returns {string}
 */
function toMobileVkUrl(url) {
    try {
        const u = new URL(url);
        if (u.hostname.startsWith("m.")) {
            return u.toString();
        }
        u.hostname = u.hostname.replace(/^(www\.)?vk\./i, "m.vk.");
        return u.toString();
    } catch {
        return url
            .replace("://vk.com", "://m.vk.com")
            .replace("://www.vk.com", "://m.vk.com");
    }
}

/**
 * @param {string} url
 * @returns {string}
 */
function toDesktopVkUrl(url) {
    try {
        const u = new URL(url);
        u.hostname = u.hostname.replace(/^m\./i, "");
        return u.toString();
    } catch {
        return url.replace("://m.vk.com", "://vk.com");
    }
}

/**
 * Extract owner_post id from wall URL.
 * @param {string} url
 * @returns {string|null}
 */
function extractPostIdFromUrl(url) {
    try {
        const u = new URL(url);
        const m = u.pathname.match(/wall(-?\d+_\d+)/i);
        if (m) return m[1];

        const w = u.searchParams.get("w");
        if (w) {
            const wm = String(w).match(/wall(-?\d+_\d+)/i);
            if (wm) return wm[1];
        }
    } catch {
        // ignore
    }
    return null;
}

module.exports = {
    scrapeComments,
    extractPostIdFromUrl,
    toMobileVkUrl,
    parentFromUrl,
    expandAllComments,
    collectPaginationUrls,
    threadRootFromPageUrl,
};
