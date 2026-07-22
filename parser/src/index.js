const express = require("express");
const scrapeRoutes = require("./routes/scrape");

const app = express();

app.use(express.json());

app.get("/health", (req, res) => {
    res.json({ status: "ok" });
});

app.use("/scrape", scrapeRoutes);

const PORT = 3000;

app.listen(PORT, () => {
    console.log(`Parser running on ${PORT}`);
});
