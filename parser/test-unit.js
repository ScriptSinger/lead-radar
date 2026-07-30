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
const { classifyPageSnapshot } = require("./src/utils/vkPageProbe");
const { ScrapeError, isScrapeError } = require("./src/utils/scrapeError");
const { isRetryable } = require("./src/utils/retry");

// isVkUrl
assert.strictEqual(isVkUrl("https://vk.com/test_group"), true);
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

// --- captcha / page probe ---
const captchaSnap = {
    url: "https://vk.com/challenge.html?tid=abc&redirect=/test_group",
    title: "Проверяем, что вы не робот",
    bodyText: "Проверяем, что вы не робот Продолжить",
    bodyHtmlSample: "<div>challenge</div>",
    bodyTextLen: 40,
    counts: {
        post_testid: 0,
        post_class: 0,
        wall_item: 0,
        wall_reply: 0,
        captcha_node: 1,
        challenge_node: 1,
    },
    hasPasswordInput: false,
    hasLoginButton: false,
};
const captcha = classifyPageSnapshot(captchaSnap);
assert.strictEqual(captcha.verdict, "captcha");
assert.ok(captcha.confidence >= 50);
assert.strictEqual(captcha.isBlocking, true);
assert.ok(captcha.signals.some((s) => s.id === "url_challenge"));

const okSnap = {
    url: "https://vk.com/test_group",
    title: "Тестовая группа",
    bodyText: "Стена группы много текста постов Регистрация Войти",
    bodyHtmlSample: "<div data-testid=post></div>",
    bodyTextLen: 500,
    counts: {
        post_testid: 6,
        post_class: 6,
        wall_item: 0,
        wall_reply: 0,
        captcha_node: 0,
        challenge_node: 0,
    },
    hasPasswordInput: false,
    hasLoginButton: true,
};
const ok = classifyPageSnapshot(okSnap);
assert.strictEqual(ok.verdict, "ok");
assert.strictEqual(ok.isBlocking, false);

// m.vk post page for comments: post visible, 0 replies, chrome has «Войти»
// must NOT be classified as login (previous false positive)
const commentPageSnap = {
    url: "https://m.vk.ru/wall-159025534_727",
    title: "Ищете парикмахерскую | ВКонтакте",
    bodyText:
        "Запись на стене Регистрация Войти Ищете парикмахерскую в Инорсе со скидкой на стрижку? Приходите",
    bodyHtmlSample: "<div class=wall_item></div>",
    bodyTextLen: 1081,
    counts: {
        post_testid: 0,
        post_class: 0,
        wall_item: 1,
        wall_reply: 0,
        captcha_node: 0,
        challenge_node: 0,
    },
    hasPasswordInput: false,
    hasLoginButton: true,
};
const commentPage = classifyPageSnapshot(commentPageSnap, {
    expect: "comments",
});
assert.strictEqual(
    commentPage.verdict,
    "ok",
    "comment page with wall_item must be ok, not login",
);
assert.strictEqual(commentPage.isBlocking, false);

const err = new ScrapeError("VK captcha", {
    code: "VK_CAPTCHA",
    diagnostics: captcha,
    retryable: false,
});
assert.ok(isScrapeError(err));
assert.strictEqual(isRetryable(err), false);

console.log("All unit checks passed.");
