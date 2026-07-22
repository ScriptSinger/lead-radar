const express = require("express");
const scrapeRoutes = require("./routes/scrape");
const { closeBrowser } = require("./browser/playwright");
const logger = require("./utils/logger");

const app = express();
const PORT = Number(process.env.PORT || 3000);

app.use(express.json({ limit: "100kb" }));

app.get("/health", (req, res) => {
    res.json({
        status: "ok",
        service: "parser",
        ts: new Date().toISOString(),
    });
});

app.use("/scrape", scrapeRoutes);

// 404
app.use((req, res) => {
    res.status(404).json({ success: false, error: "not found" });
});

// Error middleware
app.use((err, req, res, _next) => {
    logger.error("unhandled error", { error: err.message, path: req.path });
    res.status(500).json({
        success: false,
        error: err.message || "internal error",
    });
});

const server = app.listen(PORT, () => {
    logger.info("parser started", { port: PORT });
});

async function shutdown(signal) {
    logger.info("shutdown", { signal });
    server.close(async () => {
        await closeBrowser();
        process.exit(0);
    });

    // Force exit if hang
    setTimeout(() => process.exit(1), 10000).unref();
}

process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));
