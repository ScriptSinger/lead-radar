/**
 * Structured scrape failure for API + logs.
 *
 * codes:
 *   VK_CAPTCHA  — bot challenge / captcha interstitial
 *   VK_LOGIN    — login wall / auth required
 *   VK_BLOCKED  — access denied / soft ban page
 *   EMPTY_WALL  — page loaded but no posts (ambiguous)
 *   PARSE_ERROR — unexpected failure
 */
class ScrapeError extends Error {
    /**
     * @param {string} message
     * @param {{
     *   code?: string,
     *   diagnostics?: object|null,
     *   retryable?: boolean,
     *   cause?: Error,
     * }} [opts]
     */
    constructor(message, opts = {}) {
        super(message);
        this.name = "ScrapeError";
        this.code = opts.code || "PARSE_ERROR";
        this.diagnostics = opts.diagnostics || null;
        this.retryable = Boolean(opts.retryable);
        if (opts.cause) {
            this.cause = opts.cause;
        }
    }

    toJSON() {
        return {
            name: this.name,
            message: this.message,
            code: this.code,
            retryable: this.retryable,
            diagnostics: this.diagnostics,
        };
    }
}

/**
 * @param {unknown} err
 * @returns {err is ScrapeError}
 */
function isScrapeError(err) {
    return err instanceof ScrapeError || err?.name === "ScrapeError";
}

module.exports = { ScrapeError, isScrapeError };
