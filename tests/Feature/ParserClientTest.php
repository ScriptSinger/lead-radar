<?php

namespace Tests\Feature;

use App\Services\Vk\ParserClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ParserClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.parser.url' => 'http://parser.test',
            'services.parser.timeout' => 5,
        ]);
    }

    public function test_health_true_when_status_ok(): void
    {
        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'ok', 'service' => 'parser'], 200),
        ]);

        $this->assertTrue(app(ParserClient::class)->health());
    }

    public function test_health_false_on_connection_or_bad_status(): void
    {
        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'error'], 200),
        ]);

        $this->assertFalse(app(ParserClient::class)->health());

        Http::fake([
            'http://parser.test/health' => Http::response('nope', 503),
        ]);

        $this->assertFalse(app(ParserClient::class)->health());
    }

    public function test_scrape_group_returns_data_array(): void
    {
        Http::fake([
            'http://parser.test/scrape/group' => Http::response([
                'success' => true,
                'data' => [[
                    'vk_post_id' => '-1_2',
                    'text' => 'hello',
                    'url' => 'https://vk.com/wall-1_2',
                    'posted_at' => null,
                    'author_id' => null,
                ]],
            ], 200),
        ]);

        $data = app(ParserClient::class)->scrapeGroup('https://vk.com/club1', 3);

        $this->assertCount(1, $data);
        $this->assertSame('-1_2', $data[0]['vk_post_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://parser.test/scrape/group'
                && $request['url'] === 'https://vk.com/club1'
                && $request['limit'] === 3;
        });
    }

    public function test_scrape_throws_on_parser_failure_payload(): void
    {
        Http::fake([
            'http://parser.test/scrape/comments' => Http::response([
                'success' => false,
                'error' => 'captcha',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('captcha');

        app(ParserClient::class)->scrapeComments('https://vk.com/wall-1_1');
    }

    public function test_scrape_throws_on_http_error(): void
    {
        Http::fake([
            'http://parser.test/scrape/group' => Http::response(['error' => 'busy'], 429),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');

        app(ParserClient::class)->scrapeGroup('https://vk.com/x');
    }
}
