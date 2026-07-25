#!/usr/bin/env node
/**
 * One-shot VK login → save Playwright storageState for the parser.
 *
 * Usage (Docker):
 *   docker compose exec -e VK_LOGIN=... -e VK_PASSWORD=... parser node src/auth/login.js
 *
 * Or put VK_LOGIN / VK_PASSWORD in project .env and:
 *   docker compose exec parser node src/auth/login.js
 *
 * After success, restart is NOT required — next scrape loads the session file.
 * Re-run this script when session expires or captcha blocks scrapes.
 *
 * Notes:
 * - 2FA / captcha may require manual intervention (set VK_LOGIN_WAIT_MS high
 *   and watch logs; or login on a machine with headed browser and copy state).
 * - Scraping with a personal account may violate VK ToS — use at your own risk.
 */

const { chromium } = require("playwright");
const logger = require("../utils/logger");
const {
    sessionPath,
    saveStorageState,
    pageLooksLoggedIn,
} = require("./session");

const USER_AGENT =
    process.env.PARSER_USER_AGENT ||
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36";

const WAIT_MS = Number(process.env.VK_LOGIN_WAIT_MS || 15000);

async function main() {
    const login = (process.env.VK_LOGIN || "").trim();
    const password = (process.env.VK_PASSWORD || "").trim();

    if (!login || !password) {
        logger.error("vk.auth.missing_credentials", {
            hint: "Set VK_LOGIN and VK_PASSWORD env vars",
        });
        process.exit(1);
    }

    logger.info("vk.auth.login_start", {
        login_hint: login.slice(0, 3) + "***",
        session_path: sessionPath(),
        wait_ms: WAIT_MS,
    });

    const browser = await chromium.launch({
        headless: true,
        args: [
            "--disable-blink-features=AutomationControlled",
            "--no-sandbox",
            "--disable-dev-shm-usage",
        ],
    });

    const context = await browser.newContext({
        userAgent: USER_AGENT,
        locale: "ru-RU",
        viewport: { width: 1280, height: 900 },
        extraHTTPHeaders: {
            "Accept-Language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
        },
    });

    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        // Mobile login is often simpler for forms
        await page.goto("https://m.vk.com/login", {
            waitUntil: "domcontentloaded",
            timeout: 45000,
        });
        await page.waitForTimeout(2000);

        const filled = await fillLoginForm(page, login, password);
        if (!filled) {
            // Desktop fallback
            logger.info("vk.auth.try_desktop_login");
            await page.goto("https://vk.com/login", {
                waitUntil: "domcontentloaded",
                timeout: 45000,
            });
            await page.waitForTimeout(2000);
            const ok2 = await fillLoginForm(page, login, password);
            if (!ok2) {
                throw new Error(
                    "Could not find login form fields (email/pass). VK markup may have changed.",
                );
            }
        }

        await page.waitForTimeout(WAIT_MS);

        // 2FA / captcha pause
        const extraWait = Number(process.env.VK_LOGIN_2FA_WAIT_MS || 0);
        if (extraWait > 0) {
            logger.info("vk.auth.wait_2fa", {
                ms: extraWait,
                hint: "Complete 2FA/captcha if browser is headed; headless may fail",
            });
            await page.waitForTimeout(extraWait);
        }

        const url = page.url();
        const title = await page.title();
        const loggedIn = await pageLooksLoggedIn(page);

        // Soft success: cookies often enough even if UI heuristic fails
        const cookies = await context.cookies();
        const hasVkAuthCookie = cookies.some(
            (c) =>
                /remix|remixsid|p|vk_id|force_session/i.test(c.name) &&
                c.value &&
                c.value.length > 5,
        );

        logger.info("vk.auth.login_result", {
            url,
            title,
            page_looks_logged_in: loggedIn,
            has_auth_cookie: hasVkAuthCookie,
            cookie_count: cookies.length,
        });

        if (!loggedIn && !hasVkAuthCookie) {
            // Dump short body for debugging
            const snippet = await page
                .evaluate(() =>
                    (document.body?.innerText || "").slice(0, 300),
                )
                .catch(() => "");
            throw new Error(
                `Login does not look successful (url=${url} title=${title}). Body: ${snippet}`,
            );
        }

        const file = await saveStorageState(context);
        logger.info("vk.auth.login_ok", {
            path: file,
            note: "Scrapes will reuse this session automatically",
        });
        process.exit(0);
    } catch (e) {
        logger.error("vk.auth.login_failed", { error: e.message });
        process.exit(1);
    } finally {
        await context.close().catch(() => {});
        await browser.close().catch(() => {});
    }
}

/**
 * @param {import('playwright').Page} page
 * @param {string} login
 * @param {string} password
 */
async function fillLoginForm(page, login, password) {
    const emailSelectors = [
        'input[name="email"]',
        'input[name="login"]',
        "#index_email",
        'input[type="tel"]',
        'input[type="text"]',
        'input[type="email"]',
    ];
    const passSelectors = [
        'input[name="pass"]',
        'input[name="password"]',
        "#index_pass",
        'input[type="password"]',
    ];
    const submitSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        "#install_submit",
        'button:has-text("Войти")',
        'input[value="Войти"]',
    ];

    let emailEl = null;
    for (const sel of emailSelectors) {
        const loc = page.locator(sel).first();
        if ((await loc.count().catch(() => 0)) > 0) {
            try {
                if (await loc.isVisible({ timeout: 800 })) {
                    emailEl = loc;
                    break;
                }
            } catch {
                // next
            }
        }
    }

    let passEl = null;
    for (const sel of passSelectors) {
        const loc = page.locator(sel).first();
        if ((await loc.count().catch(() => 0)) > 0) {
            try {
                if (await loc.isVisible({ timeout: 800 })) {
                    passEl = loc;
                    break;
                }
            } catch {
                // next
            }
        }
    }

    if (!emailEl || !passEl) {
        logger.warn("vk.auth.form_not_found", {
            url: page.url(),
            has_email: Boolean(emailEl),
            has_pass: Boolean(passEl),
        });
        return false;
    }

    await emailEl.fill(login);
    await passEl.fill(password);

    let clicked = false;
    for (const sel of submitSelectors) {
        const loc = page.locator(sel).first();
        if ((await loc.count().catch(() => 0)) > 0) {
            try {
                if (await loc.isVisible({ timeout: 500 })) {
                    await loc.click({ timeout: 3000 });
                    clicked = true;
                    break;
                }
            } catch {
                // try next
            }
        }
    }

    if (!clicked) {
        await passEl.press("Enter");
    }

    logger.info("vk.auth.form_submitted");
    return true;
}

main();
