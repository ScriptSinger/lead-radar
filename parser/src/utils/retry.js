const logger = require("./logger");

/**
 * Retry an async function on transient failures (timeout / network).
 *
 * @param {() => Promise<T>} fn
 * @param {{ retries?: number, delayMs?: number, label?: string }} options
 * @returns {Promise<T>}
 * @template T
 */
async function withRetry(fn, options = {}) {
    const retries = options.retries ?? 2;
    const delayMs = options.delayMs ?? 1500;
    const label = options.label ?? "operation";

    let lastError;

    for (let attempt = 0; attempt <= retries; attempt++) {
        try {
            return await fn();
        } catch (error) {
            lastError = error;

            const retryable = isRetryable(error);
            const willRetry = retryable && attempt < retries;

            logger.warn(`${label} failed`, {
                attempt: attempt + 1,
                retries: retries + 1,
                retryable,
                willRetry,
                error: error.message,
            });

            if (!willRetry) {
                break;
            }

            await sleep(delayMs * (attempt + 1));
        }
    }

    throw lastError;
}

function isRetryable(error) {
    const message = String(error?.message || error || "").toLowerCase();
    const name = String(error?.name || "").toLowerCase();

    return (
        name.includes("timeout") ||
        message.includes("timeout") ||
        message.includes("net::") ||
        message.includes("navigation") ||
        message.includes("target closed") ||
        message.includes("browser has been closed") ||
        message.includes("connection") ||
        message.includes("econnreset") ||
        message.includes("socket hang up")
    );
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

module.exports = { withRetry, isRetryable, sleep };
