const { scrapeGroup } = require("./src/scrapers/vkGroup");

async function run() {
    console.log("Starting test scrape...");
    try {
        const data = await scrapeGroup({ url: "https://vk.com/halturaufa" });
        console.log("Scraped", data.length, "posts:");
        console.log(JSON.stringify(data, null, 2));
    } catch (e) {
        console.error("Error:", e);
    }
}

run();
