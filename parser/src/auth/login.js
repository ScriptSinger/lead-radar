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
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1";

const WAIT_MS = Number(process.env.VK_LOGIN_WAIT_MS || 15000);
const STEP_WAIT_MS = Number(
    process.env.VK_LOGIN_STEP_WAIT_MS ||
        process.env.VK_LOGIN_2FA_WAIT_MS ||
        20000,
);
const HEADLESS = !["0", "false", "no"].includes(
    String(process.env.VK_LOGIN_HEADLESS || "true").toLowerCase(),
);
const CHROME_EXECUTABLE_PATH =
    process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ||
    process.env.CHROME_PATH ||
    "";

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
        step_wait_ms: STEP_WAIT_MS,
        headless: HEADLESS,
        executable_path: CHROME_EXECUTABLE_PATH || undefined,
    });

    const browser = await chromium.launch({
        headless: HEADLESS,
        executablePath: CHROME_EXECUTABLE_PATH || undefined,
        args: [
            "--disable-blink-features=AutomationControlled",
            "--no-sandbox",
            "--disable-dev-shm-usage",
        ],
    });

    const context = await browser.newContext({
        userAgent: USER_AGENT,
        locale: "ru-RU",
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
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

        const filled = await fillLoginForm(page, login, password);
        if (!filled) {
            // Desktop fallback
            logger.info("vk.auth.try_desktop_login");
            await page.goto("https://vk.com/login", {
                waitUntil: "domcontentloaded",
                timeout: 45000,
            });
            const ok2 = await fillLoginForm(page, login, password);
            if (!ok2) {
                const authBlock = await detectAuthBlock(page);
                if (authBlock) {
                    throw new Error(authBlock);
                }
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

        // Verify on an authenticated-only destination. A login-page token or
        // device cookie alone must never be saved as a usable session.
        await page.goto("https://vk.com/feed", {
            waitUntil: "domcontentloaded",
            timeout: 45000,
        });
        await page.waitForTimeout(1500);

        const url = page.url();
        const title = await page.title();
        const loggedIn = await pageLooksLoggedIn(page);

        // Soft success: cookies often enough even if UI heuristic fails
        const cookies = await context.cookies();
        const hasVkAuthCookie = require("./session").hasAuthenticatedCookie(cookies);

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
        'input[name="phone"]',
        'input[name="username"]',
        "#index_email",
        "#login",
        "#email",
        'input[autocomplete="username"]',
        'input[id*="login" i]',
        'input[id*="email" i]',
        'input[id*="phone" i]',
        'input[type="tel"]',
        'input[type="email"]',
        'input[type="text"]',
    ];
    const passSelectors = [
        'input[name="pass"]',
        'input[name="password"]',
        "#index_pass",
        "#password",
        'input[autocomplete="current-password"]',
        'input[id*="pass" i]',
        'input[type="password"]',
    ];
    const submitSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        "#install_submit",
        'button:has-text("Продолжить")',
        '[role="button"]:has-text("Продолжить")',
        'button:has-text("Войти")',
        '[role="button"]:has-text("Войти")',
        'button:has-text("Log in")',
        'button:has-text("Continue")',
        'input[value="Войти"]',
    ];

    const emailEl = await waitForVisibleLocator(page, emailSelectors, 20000);
    let passEl = await findVisibleLocator(page, passSelectors);

    if (!emailEl || !passEl) {
        if (emailEl && !passEl) {
            await emailEl.fill(login);
            await clickFirstVisible(page, submitSelectors, { fallback: emailEl });

            passEl = await waitForVisibleLocator(page, passSelectors, STEP_WAIT_MS);
            if (passEl) {
                await passEl.fill(password);
                await clickFirstVisible(page, submitSelectors, { fallback: passEl });
                logger.info("vk.auth.form_submitted", { flow: "stepwise" });
                return true;
            }

            const authBlock = await detectAuthBlock(page);
            if (authBlock) {
                throw new Error(authBlock);
            }
        }

        logger.warn("vk.auth.form_not_found", {
            url: page.url(),
            has_email: Boolean(emailEl),
            has_pass: Boolean(passEl),
            frames: page.frames().map((frame) => frame.url()).slice(0, 8),
        });
        return false;
    }

    await emailEl.fill(login);
    await passEl.fill(password);

    await clickFirstVisible(page, submitSelectors, { fallback: passEl });

    logger.info("vk.auth.form_submitted", { flow: "single_page" });
    return true;
}

async function detectAuthBlock(page) {
    const frameUrls = page.frames().map((frame) => frame.url());
    const joinedUrls = frameUrls.join("\n");
    const text = (
        await Promise.all(
            page.frames().map((frame) =>
                frame
                    .locator("body")
                    .innerText({ timeout: 1000 })
                    .catch(() => ""),
            ),
        )
    ).join("\n");

    if (
        /not_robot_captcha|challenge|captcha/i.test(joinedUrls) ||
        /не робот|captcha|проверяем/i.test(text)
    ) {
        return "VK requires captcha/not-robot verification during login. Headless automated login cannot continue; retry from a machine with GUI using VK_LOGIN_HEADLESS=false and a larger VK_LOGIN_STEP_WAIT_MS, then complete the check manually.";
    }

    return null;
}

async function findVisibleLocator(page, selectors) {
    for (const frame of page.frames()) {
        for (const sel of selectors) {
            const loc = frame.locator(sel).first();
            if ((await loc.count().catch(() => 0)) > 0) {
                try {
                    if (await loc.isVisible({ timeout: 800 })) {
                        return loc;
                    }
                } catch {
                    // next
                }
            }
        }
    }

    return null;
}

async function waitForVisibleLocator(page, selectors, timeoutMs) {
    const started = Date.now();
    while (Date.now() - started < timeoutMs) {
        const loc = await findVisibleLocator(page, selectors);
        if (loc) {
            return loc;
        }
        await page.waitForTimeout(500);
    }

    return null;
}

async function clickFirstVisible(page, selectors, { fallback } = {}) {
    for (const frame of page.frames()) {
        for (const sel of selectors) {
            const loc = frame.locator(sel).first();
            if ((await loc.count().catch(() => 0)) > 0) {
                try {
                    if (await loc.isVisible({ timeout: 500 })) {
                        await loc.click({ timeout: 3000 });
                        return true;
                    }
                } catch {
                    // try next
                }
            }
        }
    }

    if (fallback) {
        await fallback.press("Enter");
        return true;
    }

    return false;
}

main();
