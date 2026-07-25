<?php

namespace Tests\Unit;

use App\Exceptions\VkScrapeException;
use App\Services\Vk\ParserClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ensures Laravel understands structured captcha/block responses from the parser.
 */
class VkPageProbeContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.parser.url' => 'http://parser.test',
            'services.parser.timeout' => 5,
        ]);
    }

    public function test_captcha_response_throws_vk_scrape_exception(): void
    {
        Http::fake([
            'http://parser.test/scrape/group' => Http::response([
                'success' => false,
                'error' => 'VK captcha (confidence=90): Проверяем, что вы не робот',
                'code' => 'VK_CAPTCHA',
                'diagnostics' => [
                    'verdict' => 'captcha',
                    'confidence' => 90,
                    'scores' => ['captcha' => 100, 'ok' => 0],
                    'signals' => [
                        ['id' => 'url_challenge', 'bucket' => 'captcha', 'weight' => 55],
                    ],
                    'page' => [
                        'url' => 'https://vk.com/challenge.html',
                        'title' => 'Проверяем, что вы не робот',
                    ],
                ],
            ], 423),
        ]);

        try {
            app(ParserClient::class)->scrapeGroup('https://vk.com/club1', 6);
            $this->fail('Expected VkScrapeException');
        } catch (VkScrapeException $e) {
            $this->assertTrue($e->isCaptcha());
            $this->assertTrue($e->isBlocking());
            $this->assertSame('VK_CAPTCHA', $e->errorCode);
            $this->assertSame(423, $e->httpStatus);
            $this->assertSame('captcha', $e->diagnostics['verdict'] ?? null);
            $this->assertSame(90, $e->diagnostics['confidence'] ?? null);
        }
    }

    public function test_infer_captcha_from_message_without_code(): void
    {
        Http::fake([
            'http://parser.test/scrape/group' => Http::response([
                'success' => false,
                'error' => 'challenge page: not a robot',
            ], 500),
        ]);

        $this->expectException(VkScrapeException::class);

        try {
            app(ParserClient::class)->scrapeGroup('https://vk.com/x', 3);
        } catch (VkScrapeException $e) {
            $this->assertSame(VkScrapeException::CODE_CAPTCHA, $e->errorCode);

            throw $e;
        }
    }
}
