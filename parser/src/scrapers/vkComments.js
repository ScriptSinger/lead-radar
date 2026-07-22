const { chromium } = require("playwright");

async function scrapeComments({ url }) {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    await page.goto(url, { waitUntil: "domcontentloaded" });

    await page.waitForTimeout(3000);

    const comments = await page.evaluate(() => {
        const items = document.querySelectorAll(".reply_text");

        return Array.from(items).map((el) => ({
            text: el.innerText,
        }));
    });

    await browser.close();

    return comments;
}

module.exports = { scrapeComments };
