/**
 * VK URL helpers and date best-effort parsing.
 */

const VK_HOST_RE = /(^|\.)vk\.(com|ru)$/i;

/**
 * @param {unknown} value
 * @returns {boolean}
 */
function isVkUrl(value) {
    if (typeof value !== "string" || !value.trim()) {
        return false;
    }

    try {
        const url = new URL(value.trim());
        return VK_HOST_RE.test(url.hostname);
    } catch {
        return false;
    }
}

/**
 * Build a canonical wall post URL from data-post-id (e.g. "-123_456").
 *
 * @param {string} vkPostId
 * @returns {string|null}
 */
function wallPostUrl(vkPostId) {
    if (!vkPostId || typeof vkPostId !== "string") {
        return null;
    }

    const id = vkPostId.trim();
    if (!/^-?\d+_\d+$/.test(id)) {
        // Non-standard id — still produce a best-effort wall link
        return `https://vk.com/wall${id}`;
    }

    return `https://vk.com/wall${id}`;
}

/**
 * Build a wall comment URL: wall{owner}_{post}?reply={commentId}
 *
 * @param {string} vkPostId e.g. "-123_456"
 * @param {string|number} vkCommentId
 * @returns {string|null}
 */
function wallCommentUrl(vkPostId, vkCommentId) {
    const postUrl = wallPostUrl(vkPostId);
    if (!postUrl || vkCommentId == null || vkCommentId === "") {
        return postUrl;
    }

    return `${postUrl}?reply=${vkCommentId}`;
}

/**
 * Best-effort parse of VK relative/absolute date strings to ISO-8601.
 * Returns null when parsing is not reliable.
 *
 * @param {string|null|undefined} raw
 * @param {Date} [now]
 * @returns {string|null}
 */
function parseVkDate(raw, now = new Date()) {
    if (!raw || typeof raw !== "string") {
        return null;
    }

    const text = raw.replace(/\s+/g, " ").trim().toLowerCase();
    if (!text) {
        return null;
    }

    // Absolute ISO-like already
    const asDate = Date.parse(raw);
    if (!Number.isNaN(asDate) && /^\d{4}-\d{2}-\d{2}/.test(raw.trim())) {
        return new Date(asDate).toISOString();
    }

    // "только что", "N секунд/минут/часов назад"
    if (text.includes("только что") || text.includes("только що")) {
        return now.toISOString();
    }

    // Note: JS \b is ASCII-only — do not use word boundaries with Cyrillic.
    // "3 ч назад", "5 мин назад", "2 часа назад", "1 д назад"
    const agoMatch = text.match(
        /(\d+)\s*(секунд[уыа]?|сек|минут[уыа]?|мин|час(?:а|ов)?|ч|дн(?:я|ей|и)?|день|д)(?=\s|$|н)/,
    );
    if (agoMatch && (text.includes("назад") || text.includes("тому"))) {
        const n = parseInt(agoMatch[1], 10);
        const unit = agoMatch[2];
        const d = new Date(now);

        if (unit.startsWith("сек")) {
            d.setSeconds(d.getSeconds() - n);
        } else if (unit.startsWith("мин")) {
            d.setMinutes(d.getMinutes() - n);
        } else if (unit.startsWith("час") || unit === "ч") {
            d.setHours(d.getHours() - n);
        } else {
            d.setDate(d.getDate() - n);
        }

        return d.toISOString();
    }

    // "сегодня в 14:30" / "вчера в 9:05"
    const timeMatch = text.match(/(?:в\s+)?(\d{1,2}):(\d{2})/);
    const hours = timeMatch ? parseInt(timeMatch[1], 10) : 12;
    const minutes = timeMatch ? parseInt(timeMatch[2], 10) : 0;

    if (text.startsWith("сегодня") || text.includes("сегодня")) {
        const d = new Date(now);
        d.setHours(hours, minutes, 0, 0);
        return d.toISOString();
    }

    if (text.startsWith("вчера") || text.includes("вчера")) {
        const d = new Date(now);
        d.setDate(d.getDate() - 1);
        d.setHours(hours, minutes, 0, 0);
        return d.toISOString();
    }

    // "5 июл в 12:00" / "5 июля 2025"
    const months = {
        янв: 0,
        фев: 1,
        мар: 2,
        апр: 3,
        мая: 4,
        май: 4,
        июн: 5,
        июл: 6,
        авг: 7,
        сен: 8,
        окт: 9,
        ноя: 10,
        дек: 11,
    };

    const dateMatch = text.match(
        /(\d{1,2})\s+([а-яё]{3,8})(?:\s+(\d{4}))?(?:\s+в\s+(\d{1,2}):(\d{2}))?/,
    );

    if (dateMatch) {
        const day = parseInt(dateMatch[1], 10);
        const monKey = dateMatch[2].slice(0, 3);
        const month = months[monKey];

        if (month != null) {
            const year = dateMatch[3]
                ? parseInt(dateMatch[3], 10)
                : now.getFullYear();
            const h = dateMatch[4] ? parseInt(dateMatch[4], 10) : hours;
            const m = dateMatch[5] ? parseInt(dateMatch[5], 10) : minutes;
            const d = new Date(year, month, day, h, m, 0, 0);

            // If date is in the future (e.g. Dec when now is Jan), roll back a year
            if (!dateMatch[3] && d.getTime() - now.getTime() > 86400000 * 2) {
                d.setFullYear(d.getFullYear() - 1);
            }

            return d.toISOString();
        }
    }

    return null;
}

/**
 * Normalize a scraped post to the API contract.
 *
 * @param {object} raw
 * @returns {{
 *   vk_post_id: string,
 *   text: string,
 *   url: string,
 *   posted_at: string|null,
 *   author_id: number|null,
 *   posted_at_raw: string|null
 * }}
 */
function normalizePost(raw) {
    const vkPostId = String(raw.vk_post_id ?? "").trim();
    const postedAtRaw =
        raw.posted_at_raw != null
            ? String(raw.posted_at_raw)
            : raw.posted_at != null && typeof raw.posted_at === "string"
              ? raw.posted_at
              : null;

    const url =
        (raw.url && String(raw.url)) || wallPostUrl(vkPostId) || "";

    let authorId = null;
    if (raw.author_id != null && raw.author_id !== "") {
        const n = Number(raw.author_id);
        authorId = Number.isFinite(n) ? n : null;
    }

    return {
        vk_post_id: vkPostId,
        text: raw.text != null ? String(raw.text) : "",
        url,
        posted_at: parseVkDate(postedAtRaw) || (isIso(raw.posted_at) ? raw.posted_at : null),
        author_id: authorId,
        posted_at_raw: postedAtRaw,
    };
}

/**
 * Normalize a scraped comment to the API contract.
 *
 * @param {object} raw
 * @param {string} [fallbackPostId]
 */
function normalizeComment(raw, fallbackPostId = null) {
    const vkCommentId = String(raw.vk_comment_id ?? "").trim();
    const postId = String(raw.vk_post_id ?? fallbackPostId ?? "").trim() || null;
    const parent =
        raw.parent_comment_id != null && raw.parent_comment_id !== ""
            ? String(raw.parent_comment_id)
            : null;

    const postedAtRaw =
        raw.posted_at_raw != null
            ? String(raw.posted_at_raw)
            : raw.posted_at != null && typeof raw.posted_at === "string"
              ? raw.posted_at
              : null;

    let authorId = null;
    if (raw.author_id != null && raw.author_id !== "") {
        const n = Number(raw.author_id);
        authorId = Number.isFinite(n) ? n : null;
    }

    const url =
        (raw.url && String(raw.url)) ||
        (postId ? wallCommentUrl(postId, vkCommentId) : null) ||
        "";

    return {
        vk_comment_id: vkCommentId,
        vk_post_id: postId,
        parent_comment_id: parent,
        text: raw.text != null ? String(raw.text) : "",
        url,
        posted_at: parseVkDate(postedAtRaw) || (isIso(raw.posted_at) ? raw.posted_at : null),
        author_id: authorId,
        posted_at_raw: postedAtRaw,
    };
}

function isIso(value) {
    return (
        typeof value === "string" &&
        !Number.isNaN(Date.parse(value)) &&
        /^\d{4}-\d{2}-\d{2}T/.test(value)
    );
}

module.exports = {
    isVkUrl,
    wallPostUrl,
    wallCommentUrl,
    parseVkDate,
    normalizePost,
    normalizeComment,
};
