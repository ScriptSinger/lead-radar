const express = require("express");
const scrapeRoutes = require("./routes/scrape");
const { closeBrowser } = require("./browser/playwright");
const { sessionStatus } = require("./auth/session");
const logger = require("./utils/logger");

const app = express();
const PORT = Number(process.env.PORT || 3000);
const SERVICE_TOKEN = process.env.PARSER_SERVICE_TOKEN || "";

app.use(express.json({ limit: "100kb" }));

// The parser controls a browser session and must not be reachable as a public
// scraping endpoint. Docker keeps it on the private network; this is the
// second, application-level boundary.
app.use((req, res, next) => {
    if (!SERVICE_TOKEN) {
        return res.status(503).json({
            success: false,
            error: "parser service token is not configured",
        });
    }

    const authorization = req.get("authorization") || "";
    if (authorization !== `Bearer ${SERVICE_TOKEN}`) {
        return res.status(401).json({ success: false, error: "unauthorized" });
    }

    next();
});

app.get("/health", (req, res) => {
    const auth = sessionStatus();
    res.json({
        status: "ok",
        service: "parser",
        ts: new Date().toISOString(),
        auth: {
            session_loaded: auth.enabled,
            session_path: auth.path,
            session_mtime: auth.mtime,
            cookie_count: auth.cookie_count,
            login_env_set: auth.login_env_set,
        },
    });
});

app.get("/auth/status", (req, res) => {
    res.json({ success: true, auth: sessionStatus() });
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
