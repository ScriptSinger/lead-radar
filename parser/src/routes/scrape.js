const express = require("express");
const router = express.Router();

const { scrapeGroup } = require("../scrapers/vkGroup");
const { scrapeComments } = require("../scrapers/vkComments");

router.post("/group", async (req, res) => {
    try {
        const { url, limit } = req.body;

        const data = await scrapeGroup({ url, limit });

        res.json({ success: true, data });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

router.post("/comments", async (req, res) => {
    try {
        const { url } = req.body;

        const data = await scrapeComments({ url });

        res.json({ success: true, data });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

module.exports = router;
