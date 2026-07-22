/**
 * Structured JSON logger for the parser service.
 */

function log(level, message, meta = {}) {
    const entry = {
        ts: new Date().toISOString(),
        level,
        message,
        ...meta,
    };

    const line = JSON.stringify(entry);

    if (level === "error") {
        console.error(line);
    } else {
        console.log(line);
    }
}

module.exports = {
    info: (message, meta) => log("info", message, meta),
    warn: (message, meta) => log("warn", message, meta),
    error: (message, meta) => log("error", message, meta),
    debug: (message, meta) => log("debug", message, meta),
};
