const express = require("express");
const router = express.Router();

const { scrapeGroup, MAX_LIMIT } = require("../scrapers/vkGroup");
const { scrapeComments } = require("../scrapers/vkComments");
const { isVkUrl } = require("../utils/vk");
const { withRetry } = require("../utils/retry");
const logger = require("../utils/logger");

/**
 * API contract
 * ------------
 * POST /scrape/group
 *   body: { url: string, limit?: number }
 *   200: { success: true, data: Post[] }
 *   4xx/5xx: { success: false, error: string }
 *
 * Post = {
 *   vk_post_id: string,
 *   text: string,
 *   url: string,
 *   posted_at: string|null,
 *   author_id: number|null,
 *   posted_at_raw: string|null
 * }
 *
 * POST /scrape/comments
 *   body: { url: string }
 *   200: { success: true, data: Comment[] }
 *
 * Comment = {
 *   vk_comment_id: string,
 *   vk_post_id: string|null,
 *   parent_comment_id: string|null,
 *   text: string,
 *   url: string,
 *   posted_at: string|null,
 *   author_id: number|null,
 *   posted_at_raw: string|null
 * }
 */

router.post("/group", async (req, res) => {
    const started = Date.now();
    const { url, limit } = req.body || {};

    const validationError = validateUrl(url);
    if (validationError) {
        return res.status(400).json({ success: false, error: validationError });
    }

    if (limit != null && (Number.isNaN(Number(limit)) || Number(limit) < 1)) {
        return res.status(400).json({
            success: false,
            error: `limit must be a positive number (max ${MAX_LIMIT})`,
        });
    }

    try {
        const data = await withRetry(
            () => scrapeGroup({ url: String(url).trim(), limit }),
            { label: "scrapeGroup", retries: 2 },
        );

        logger.info("POST /scrape/group ok", {
            url,
            count: data.length,
            ms: Date.now() - started,
        });

        return res.json({ success: true, data });
    } catch (e) {
        logger.error("POST /scrape/group failed", {
            url,
            error: e.message,
            ms: Date.now() - started,
        });

        return res.status(statusForError(e)).json({
            success: false,
            error: e.message || "scrape failed",
        });
    }
});

router.post("/comments", async (req, res) => {
    const started = Date.now();
    const { url } = req.body || {};

    const validationError = validateUrl(url);
    if (validationError) {
        return res.status(400).json({ success: false, error: validationError });
    }

    try {
        const data = await withRetry(
            () => scrapeComments({ url: String(url).trim() }),
            { label: "scrapeComments", retries: 2 },
        );

        logger.info("POST /scrape/comments ok", {
            url,
            count: data.length,
            ms: Date.now() - started,
        });

        return res.json({ success: true, data });
    } catch (e) {
        logger.error("POST /scrape/comments failed", {
            url,
            error: e.message,
            ms: Date.now() - started,
        });

        return res.status(statusForError(e)).json({
            success: false,
            error: e.message || "scrape failed",
        });
    }
});

function validateUrl(url) {
    if (url == null || String(url).trim() === "") {
        return "url is required";
    }

    if (!isVkUrl(String(url))) {
        return "url must be a valid vk.com / vk.ru link";
    }

    return null;
}

function statusForError(error) {
    const message = String(error?.message || "").toLowerCase();

    if (message.includes("timeout")) {
        return 504;
    }

    if (message.includes("invalid") || message.includes("required")) {
        return 400;
    }

    return 500;
}

module.exports = router;
