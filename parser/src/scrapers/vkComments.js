const { withPage, goto } = require("../browser/playwright");
const { normalizeComment } = require("../utils/vk");
const logger = require("../utils/logger");

/**
 * Scrape comments for a VK wall post.
 *
 * Prefer m.vk.com — desktop often hides comments for anonymous sessions.
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
            await expandComments(page);

            let comments = await page.evaluate(extractMobileComments, postIdFromUrl);

            if (!comments.length) {
                const desktopUrl = toDesktopVkUrl(url);
                logger.info("scrapeComments mobile empty, try desktop", {
                    desktopUrl,
                });
                await goto(page, desktopUrl, {
                    waitMs: Math.max(waitMs, 4000),
                });
                await expandComments(page);
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

    logger.info("scrapeComments done", { url, count: data.length });

    return data;
}

/**
 * Runs in browser context (m.vk.com).
 * @param {string|null} fallbackPostId
 */
function extractMobileComments(fallbackPostId) {
    const result = [];
    const seen = new Set();
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

        if (dateLink) {
            const href = dateLink.getAttribute("href") || "";
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

        let parentCommentId = null;
        const thread = item.closest("[id^='RepliesThread']");
        if (thread && thread.id) {
            const pm = thread.id.match(/RepliesThread(\d+)/);
            if (pm) parentCommentId = pm[1];
        }

        let authorId = null;
        const authorEl =
            item.querySelector(".ReplyItem__name") ||
            item.querySelector("a.author");
        if (authorEl) {
            const href = authorEl.getAttribute("href") || "";
            const am = href.match(/(?:id|club|public)(-?\d+)/i);
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
            parent_comment_id: null,
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
 * @param {import('playwright').Page} page
 */
async function expandComments(page) {
    const selectors = [
        'a:has-text("Показать все комментарии")',
        'button:has-text("Показать все комментарии")',
        'a:has-text("Показать комментарии")',
        'button:has-text("Показать комментарии")',
        ".replies_next",
        ".js-replies_next_link",
        "a.RepliesShowMore",
    ];

    for (const sel of selectors) {
        try {
            const el = page.locator(sel).first();
            if (await el.isVisible({ timeout: 600 })) {
                await el.click({ timeout: 2000 });
                await page.waitForTimeout(1500);
            }
        } catch {
            // optional UI control
        }
    }
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
};
