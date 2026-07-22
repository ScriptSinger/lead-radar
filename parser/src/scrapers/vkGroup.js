const { withPage, goto } = require("../browser/playwright");
const { normalizePost } = require("../utils/vk");
const logger = require("../utils/logger");

const DEFAULT_LIMIT = 6;
const MAX_LIMIT = 30;

/**
 * Scrape recent wall posts from a VK group/public page.
 *
 * Contract item:
 * {
 *   vk_post_id: string,
 *   text: string,
 *   url: string,
 *   posted_at: string|null,   // ISO-8601 when parseable
 *   author_id: number|null,
 *   posted_at_raw: string|null
 * }
 *
 * @param {{ url: string, limit?: number }} params
 * @returns {Promise<object[]>}
 */
async function scrapeGroup({ url, limit = DEFAULT_LIMIT }) {
    const take = clampLimit(limit);

    logger.info("scrapeGroup start", { url, limit: take });

    const rawPosts = await withPage(
        async (page, { waitMs }) => {
            await goto(page, url, { waitMs });

            // Prefer modern wall markup; fall back after a short extra wait
            const hasPosts = await page
                .locator('[data-testid="post"], .post, ._post')
                .first()
                .waitFor({ state: "attached", timeout: 8000 })
                .then(() => true)
                .catch(() => false);

            if (!hasPosts) {
                // One more settle for slow SPA
                await page.waitForTimeout(2000);
            }

            const posts = await page.evaluate((max) => {
                const selectors = [
                    '[data-testid="post"]',
                    ".post._post",
                    "div._post.post",
                    ".wall_item",
                ];

                /** @type {Element[]} */
                let nodes = [];
                for (const sel of selectors) {
                    nodes = Array.from(document.querySelectorAll(sel));
                    if (nodes.length) break;
                }

                const result = [];
                const seen = new Set();

                for (const post of nodes) {
                    const postId =
                        post.getAttribute("data-post-id") ||
                        post.getAttribute("data-id") ||
                        extractIdFromDomId(post.id);

                    if (!postId || seen.has(postId)) continue;
                    seen.add(postId);

                    const textEl =
                        post.querySelector(
                            '[data-testid="post-content-container"]',
                        ) ||
                        post.querySelector(".wall_post_text") ||
                        post.querySelector(".wall_text") ||
                        post.querySelector(".pi_text");

                    const dateEl =
                        post.querySelector(
                            '[data-testid="post_date_block_preview"]',
                        ) ||
                        post.querySelector("time") ||
                        post.querySelector(".post_date") ||
                        post.querySelector("a.wi_date") ||
                        post.querySelector("[data-testid='post-date']");

                    const authorEl =
                        post.querySelector("[data-testid='post_author_name']") ||
                        post.querySelector("a.author") ||
                        post.querySelector(".post_author a");

                    let authorId = null;
                    if (authorEl) {
                        const href = authorEl.getAttribute("href") || "";
                        const m = href.match(/(?:id|club|public|event)(-?\d+)/i);
                        if (m) authorId = m[1];
                    }

                    // Prefer explicit wall link in the date block
                    let url = null;
                    const linkEl =
                        post.querySelector(
                            'a[href*="wall"][data-testid="post_date_block_preview"]',
                        ) ||
                        post.querySelector('a[href*="/wall"]') ||
                        dateEl?.closest?.("a") ||
                        (dateEl?.tagName === "A" ? dateEl : null);

                    if (linkEl) {
                        const href = linkEl.getAttribute("href") || "";
                        if (href.includes("wall")) {
                            url = href.startsWith("http")
                                ? href.split("?")[0]
                                : `https://vk.com${href.split("?")[0]}`;
                        }
                    }

                    const text = textEl?.innerText?.trim() || "";
                    const postedAtRaw =
                        dateEl?.getAttribute?.("datetime") ||
                        dateEl?.textContent?.trim() ||
                        null;

                    result.push({
                        vk_post_id: postId,
                        text,
                        url,
                        posted_at: postedAtRaw,
                        posted_at_raw: postedAtRaw,
                        author_id: authorId,
                    });

                    if (result.length >= max) break;
                }

                function extractIdFromDomId(id) {
                    if (!id) return null;
                    // e.g. post-123456_789
                    const m = String(id).match(/(-?\d+_\d+)/);
                    return m ? m[1] : null;
                }

                return result;
            }, take);

            return posts;
        },
        { label: "scrapeGroup" },
    );

    if (!rawPosts.length) {
        logger.warn("scrapeGroup empty result", { url });
    }

    const data = rawPosts
        .map((p) => normalizePost(p))
        .filter((p) => p.vk_post_id);

    logger.info("scrapeGroup done", { url, count: data.length });

    return data;
}

function clampLimit(limit) {
    const n = Number(limit);
    if (!Number.isFinite(n) || n <= 0) {
        return DEFAULT_LIMIT;
    }
    return Math.min(Math.floor(n), MAX_LIMIT);
}

module.exports = { scrapeGroup, DEFAULT_LIMIT, MAX_LIMIT };
