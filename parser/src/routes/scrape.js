const express = require("express");
const router = express.Router();

const { scrapeGroup, MAX_LIMIT } = require("../scrapers/vkGroup");
const { scrapeComments } = require("../scrapers/vkComments");
const { isVkUrl } = require("../utils/vk");
const { withRetry } = require("../utils/retry");
const { isScrapeError } = require("../utils/scrapeError");
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
        return sendScrapeError(res, e, {
            route: "POST /scrape/group",
            url,
            started,
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
        return sendScrapeError(res, e, {
            route: "POST /scrape/comments",
            url,
            started,
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
    if (isScrapeError(error)) {
        if (
            error.code === "VK_CAPTCHA" ||
            error.code === "VK_LOGIN" ||
            error.code === "VK_BLOCKED"
        ) {
            // 423 Locked — clear machine-readable block (not a random 500)
            return 423;
        }
        if (error.code === "EMPTY_WALL") {
            return 404;
        }
    }

    const message = String(error?.message || "").toLowerCase();

    if (message.includes("timeout")) {
        return 504;
    }

    if (message.includes("invalid") || message.includes("required")) {
        return 400;
    }

    return 500;
}

/**
 * Structured error response + log for operators.
 * @param {import('express').Response} res
 * @param {Error} e
 * @param {{ route: string, url: string, started: number }} ctx
 */
function sendScrapeError(res, e, ctx) {
    const code = isScrapeError(e) ? e.code : "PARSE_ERROR";
    const diagnostics = isScrapeError(e) ? e.diagnostics : null;
    const ms = Date.now() - ctx.started;

    logger.error(`${ctx.route} failed`, {
        url: ctx.url,
        code,
        error: e.message,
        retryable: isScrapeError(e) ? e.retryable : true,
        diagnostics,
        ms,
    });

    return res.status(statusForError(e)).json({
        success: false,
        error: e.message || "scrape failed",
        code,
        diagnostics,
    });
}

module.exports = router;
