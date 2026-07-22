const { chromium } = require("playwright");

async function scrapeGroup({ url }) {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    await page.goto(url, { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(5000);

    const posts = await page.evaluate(() => {
        const nodes = document.querySelectorAll('[data-testid="post"]');

        const result = [];

        for (let i = 0; i < nodes.length; i++) {
            const post = nodes[i];

            // главный атрибут поста
            const postId = post.getAttribute("data-post-id");
            if (!postId) continue;

            // текст через data-testid
            const textEl = post.querySelector(
                '[data-testid="post-content-container"]',
            );
            const dateEl = post.querySelector(
                '[data-testid="post_date_block_preview"]',
            );

            const text = textEl?.innerText?.trim() || "";
            const postedAt = dateEl?.textContent?.trim() || null;

            result.push({
                vk_post_id: postId,
                text,
                posted_at: postedAt,
            });

            if (result.length >= 6) break;
        }

        return result;
    });

    await browser.close();

    return posts;
}

module.exports = { scrapeGroup };
