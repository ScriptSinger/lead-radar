const { scrapeGroup } = require("./src/scrapers/vkGroup");
const { scrapeComments } = require("./src/scrapers/vkComments");
const { closeBrowser } = require("./src/browser/playwright");

async function run() {
    const url = process.argv[2] || "https://vk.com/halturaufa";
    const limit = Number(process.argv[3] || 6);
    const mode = process.argv[4] || "group"; // group | comments

    console.log("Starting test scrape...", { url, limit, mode });

    try {
        if (mode === "comments") {
            const data = await scrapeComments({ url });
            console.log("Scraped", data.length, "comments:");
            console.log(JSON.stringify(data, null, 2));
        } else {
            const data = await scrapeGroup({ url, limit });
            console.log("Scraped", data.length, "posts:");
            console.log(JSON.stringify(data, null, 2));
        }
    } catch (e) {
        console.error("Error:", e);
        process.exitCode = 1;
    } finally {
        await closeBrowser();
    }
}

run();
