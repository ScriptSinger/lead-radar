const { chromium } = require("playwright");
const logger = require("../utils/logger");
const { sleep } = require("../utils/retry");

/** Realistic desktop Chrome UA to reduce headless blocks. */
const USER_AGENT =
    process.env.PARSER_USER_AGENT ||
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36";

const NAV_TIMEOUT_MS = Number(process.env.PARSER_NAV_TIMEOUT_MS || 45000);
const DEFAULT_WAIT_MS = Number(process.env.PARSER_PAGE_WAIT_MS || 4000);
const MIN_GAP_MS = Number(process.env.PARSER_REQUEST_GAP_MS || 1500);

/** @type {import('playwright').Browser|null} */
let browser = null;

/** Serialize scrapes + enforce gap between navigations. */
let chain = Promise.resolve();
let lastNavigationAt = 0;

/**
 * Launch (or reuse) a single Chromium process for the whole service lifetime.
 */
async function getBrowser() {
    if (browser && browser.isConnected()) {
        return browser;
    }

    logger.info("launching browser");

    browser = await chromium.launch({
        headless: true,
        args: [
            "--disable-blink-features=AutomationControlled",
            "--no-sandbox",
            "--disable-dev-shm-usage",
        ],
    });

    browser.on("disconnected", () => {
        logger.warn("browser disconnected");
        browser = null;
    });

    return browser;
}

/**
 * Run work inside a fresh browser context (isolated cookies/storage).
 * Always closes the context; keeps the browser process alive.
 *
 * @template T
 * @param {(page: import('playwright').Page) => Promise<T>} fn
 * @param {{ waitMs?: number, label?: string }} [options]
 * @returns {Promise<T>}
 */
function withPage(fn, options = {}) {
    const waitMs = options.waitMs ?? DEFAULT_WAIT_MS;
    const label = options.label ?? "scrape";

    const run = async () => {
        const gap = MIN_GAP_MS - (Date.now() - lastNavigationAt);
        if (gap > 0) {
            await sleep(gap);
        }

        const b = await getBrowser();
        const context = await b.newContext({
            userAgent: USER_AGENT,
            locale: "ru-RU",
            viewport: { width: 1365, height: 900 },
            extraHTTPHeaders: {
                "Accept-Language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
            },
        });

        const page = await context.newPage();
        page.setDefaultTimeout(NAV_TIMEOUT_MS);
        page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

        try {
            const result = await fn(page, { waitMs });
            return result;
        } finally {
            lastNavigationAt = Date.now();
            await context.close().catch((err) => {
                logger.warn("context close failed", {
                    label,
                    error: err.message,
                });
            });
        }
    };

    // Queue concurrent HTTP requests so we don't open many pages at once
    const job = chain.then(run, run);
    chain = job.then(
        () => undefined,
        () => undefined,
    );

    return job;
}

/**
 * Navigate and wait for network/DOM settle.
 *
 * @param {import('playwright').Page} page
 * @param {string} url
 * @param {{ waitMs?: number }} [options]
 */
async function goto(page, url, options = {}) {
    const waitMs = options.waitMs ?? DEFAULT_WAIT_MS;

    lastNavigationAt = Date.now();

    await page.goto(url, {
        waitUntil: "domcontentloaded",
        timeout: NAV_TIMEOUT_MS,
    });

    // Give SPA wall time to render posts/comments
    await page.waitForTimeout(waitMs);
}

/**
 * Graceful shutdown — close browser process.
 */
async function closeBrowser() {
    if (!browser) {
        return;
    }

    try {
        await browser.close();
        logger.info("browser closed");
    } catch (err) {
        logger.warn("browser close error", { error: err.message });
    } finally {
        browser = null;
    }
}

module.exports = {
    getBrowser,
    withPage,
    goto,
    closeBrowser,
    USER_AGENT,
    NAV_TIMEOUT_MS,
};
