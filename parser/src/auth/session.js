/**
 * VK Playwright storageState (cookies + localStorage) helpers.
 *
 * Session file is created by: node src/auth/login.js
 * and reused by browser contexts in scrapes.
 */

const fs = require("fs");
const path = require("path");
const logger = require("../utils/logger");

const DEFAULT_SESSION_PATH = path.join(
    __dirname,
    "..",
    "..",
    "data",
    "vk-storage-state.json",
);

/**
 * Absolute path to Playwright storageState JSON.
 */
function sessionPath() {
    const fromEnv = process.env.VK_SESSION_PATH || process.env.PARSER_VK_SESSION_PATH;
    if (fromEnv && String(fromEnv).trim() !== "") {
        return path.isAbsolute(fromEnv)
            ? fromEnv
            : path.resolve(process.cwd(), fromEnv);
    }
    return DEFAULT_SESSION_PATH;
}

function hasSessionFile() {
    try {
        return fs.existsSync(sessionPath()) && fs.statSync(sessionPath()).size > 50;
    } catch {
        return false;
    }
}

/**
 * @returns {object|null} storageState for browser.newContext({ storageState })
 */
function loadStorageState() {
    const file = sessionPath();
    if (!hasSessionFile()) {
        return null;
    }
    try {
        const raw = fs.readFileSync(file, "utf8");
        const json = JSON.parse(raw);
        if (!json || !Array.isArray(json.cookies)) {
            logger.warn("vk.auth.session_invalid", { path: file });
            return null;
        }
        return json;
    } catch (e) {
        logger.warn("vk.auth.session_read_failed", {
            path: file,
            error: e.message,
        });
        return null;
    }
}

/**
 * @param {import('playwright').BrowserContext} context
 */
async function saveStorageState(context) {
    const file = sessionPath();
    const dir = path.dirname(file);
    fs.mkdirSync(dir, { recursive: true });
    await context.storageState({ path: file });
    logger.info("vk.auth.session_saved", {
        path: file,
        bytes: fs.statSync(file).size,
    });
    return file;
}

/**
 * Lightweight status for /health (no browser).
 */
function sessionStatus() {
    const file = sessionPath();
    const exists = hasSessionFile();
    let mtime = null;
    let cookieCount = null;
    if (exists) {
        try {
            mtime = fs.statSync(file).mtime.toISOString();
            const json = JSON.parse(fs.readFileSync(file, "utf8"));
            cookieCount = Array.isArray(json.cookies) ? json.cookies.length : 0;
        } catch {
            // ignore
        }
    }
    return {
        enabled: exists,
        path: file,
        mtime,
        cookie_count: cookieCount,
        login_env_set: Boolean(
            process.env.VK_LOGIN && process.env.VK_PASSWORD,
        ),
    };
}

/**
 * Heuristic: does this page look like we are logged into VK?
 * @param {import('playwright').Page} page
 */
async function pageLooksLoggedIn(page) {
    try {
        return await page.evaluate(() => {
            const href = location.href || "";
            const text = (document.body?.innerText || "").slice(0, 2000);
            // Explicit login destinations
            if (/login\.vk|\/login|act=login/i.test(href)) {
                return false;
            }
            // Password form = not logged in
            if (document.querySelector('input[type="password"]')) {
                return false;
            }
            // Common logged-in chrome (m.vk / desktop)
            if (
                document.querySelector(
                    "#l_pr, #top_profile_link, .TopNavBtn__profile, [data-testid='leftmenu_profile'], .owner_panel, .HeaderNav__profile",
                )
            ) {
                return true;
            }
            // Cookie presence is checked server-side; here avoid promo "Войдите в аккаунт"
            if (/выйти|log out|мой профиль/i.test(text)) {
                return true;
            }
            return false;
        });
    } catch {
        return false;
    }
}

module.exports = {
    sessionPath,
    hasSessionFile,
    loadStorageState,
    saveStorageState,
    sessionStatus,
    pageLooksLoggedIn,
};
