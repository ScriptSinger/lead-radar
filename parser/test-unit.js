/**
 * Lightweight unit checks (no browser).
 * Run: node test-unit.js
 */
const assert = require("assert");
const {
    isVkUrl,
    wallPostUrl,
    wallCommentUrl,
    parseVkDate,
    normalizePost,
    normalizeComment,
} = require("./src/utils/vk");

// isVkUrl
assert.strictEqual(isVkUrl("https://vk.com/halturaufa"), true);
assert.strictEqual(isVkUrl("https://m.vk.com/wall-1_2"), true);
assert.strictEqual(isVkUrl("https://vk.ru/public1"), true);
assert.strictEqual(isVkUrl("https://google.com"), false);
assert.strictEqual(isVkUrl(""), false);
assert.strictEqual(isVkUrl(null), false);

// wall urls
assert.strictEqual(wallPostUrl("-123_456"), "https://vk.com/wall-123_456");
assert.strictEqual(
    wallCommentUrl("-123_456", "99"),
    "https://vk.com/wall-123_456?reply=99",
);

// dates
const now = new Date("2026-07-22T12:00:00+05:00");
assert.ok(parseVkDate("сегодня в 10:30", now));
assert.ok(parseVkDate("вчера в 18:00", now));
assert.ok(parseVkDate("5 минут назад", now));
assert.ok(parseVkDate("2 часа назад", now));
assert.ok(parseVkDate("3 ч назад", now));
assert.ok(parseVkDate("10 мин назад", now));
assert.ok(parseVkDate("5 июл в 12:00", now));
assert.strictEqual(parseVkDate("nonsense", now), null);

// normalize post
const post = normalizePost({
    vk_post_id: "-1_2",
    text: "hi",
    posted_at: "сегодня в 10:00",
});
assert.strictEqual(post.vk_post_id, "-1_2");
assert.strictEqual(post.url, "https://vk.com/wall-1_2");
assert.strictEqual(post.text, "hi");
assert.ok(post.posted_at === null || typeof post.posted_at === "string");

// normalize comment
const comment = normalizeComment(
    {
        vk_comment_id: "10",
        text: "need job",
        posted_at: "час назад",
    },
    "-1_2",
);
assert.strictEqual(comment.vk_comment_id, "10");
assert.strictEqual(comment.vk_post_id, "-1_2");
assert.ok(comment.url.includes("reply=10"));

console.log("All unit checks passed.");
