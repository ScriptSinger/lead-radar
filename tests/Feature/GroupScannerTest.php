<?php

namespace Tests\Feature;

use App\Exceptions\ParserUnavailableException;
use App\Models\Keyword;
use App\Models\Lead;
use App\Models\ScanRun;
use App\Models\VkGroup;
use App\Models\VkPost;
use App\Services\Vk\GroupScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GroupScannerTest extends TestCase
{
    use RefreshDatabase;

    private VkGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'services.telegram.notify_enabled' => false,
            'services.parser.url' => 'http://parser.test',
            'services.parser.timeout' => 5,
        ]);

        $this->group = VkGroup::query()->create([
            'name' => 'Scan Fixture',
            'url' => 'https://vk.com/fixture_group',
            'active' => true,
        ]);
    }

    public function test_scan_upserts_posts_matches_leads_and_writes_scan_run(): void
    {
        Keyword::query()->create(['word' => 'холодильник', 'type' => 'both']);

        $this->fakeParserHealthy([
            [
                'vk_post_id' => '-42_100',
                'text' => 'Нужен ремонт холодильника',
                'url' => 'https://vk.com/wall-42_100',
                'posted_at' => '2026-01-15T10:00:00+00:00',
                'author_id' => 9,
            ],
            [
                'vk_post_id' => '-42_101',
                'text' => 'Продаю стол',
                'url' => 'https://vk.com/wall-42_101',
                'posted_at' => '2026-01-16T10:00:00+00:00',
                'author_id' => 9,
            ],
        ]);

        $stats = app(GroupScanner::class)->scan($this->group, limit: 6, withComments: false, trigger: 'test');

        $this->assertSame(2, $stats['posts_fetched']);
        $this->assertSame(2, $stats['posts_created']);
        $this->assertSame(0, $stats['posts_updated']);
        $this->assertSame(1, $stats['leads_created']);
        $this->assertNotNull($stats['scan_run_id']);
        $this->assertGreaterThan(0, $stats['duration_ms']);

        $this->assertDatabaseCount('vk_posts', 2);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', [
            'source_type' => 'post',
            'status' => 'new',
        ]);

        $run = ScanRun::query()->findOrFail($stats['scan_run_id']);
        $this->assertSame(ScanRun::STATUS_SUCCESS, $run->status);
        $this->assertSame('test', $run->trigger);
        $this->assertSame(2, $run->posts_fetched);
        $this->assertSame(1, $run->leads_created);

        $this->group->refresh();
        $this->assertNotNull($this->group->last_scan_at);

        // Second scan: upsert updates existing posts, no duplicate leads
        $stats2 = app(GroupScanner::class)->scan($this->group, limit: 6, withComments: false, trigger: 'test');
        $this->assertSame(0, $stats2['posts_created']);
        $this->assertSame(2, $stats2['posts_updated']);
        $this->assertSame(0, $stats2['leads_created']);
        $this->assertSame(1, $stats2['leads_updated']);
        $this->assertDatabaseCount('vk_posts', 2);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame(2, ScanRun::query()->where('status', ScanRun::STATUS_SUCCESS)->count());
    }

    public function test_scan_with_comments_persists_comment_leads(): void
    {
        Keyword::query()->create(['word' => 'стиральная', 'type' => 'comment']);

        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'ok'], 200),
            'http://parser.test/scrape/group' => Http::response([
                'success' => true,
                'data' => [[
                    'vk_post_id' => '-7_1',
                    'text' => 'Тема без ключа',
                    'url' => 'https://vk.com/wall-7_1',
                    'posted_at' => '2026-02-01T12:00:00+00:00',
                    'author_id' => 1,
                ]],
            ], 200),
            'http://parser.test/scrape/comments' => Http::response([
                'success' => true,
                'data' => [[
                    'vk_comment_id' => '9001',
                    'vk_post_id' => '-7_1',
                    'parent_comment_id' => null,
                    'text' => 'Стиральная машина течёт',
                    'url' => 'https://vk.com/wall-7_1?reply=9001',
                    'posted_at' => '2026-02-01T13:00:00+00:00',
                    'author_id' => 2,
                ]],
            ], 200),
        ]);

        $stats = app(GroupScanner::class)->scan($this->group, limit: 3, withComments: true, trigger: 'test');

        $this->assertSame(1, $stats['posts_created']);
        $this->assertSame(1, $stats['comments_fetched']);
        $this->assertSame(1, $stats['comments_created']);
        $this->assertSame(1, $stats['leads_created']);
        $this->assertDatabaseCount('vk_comments', 1);
        $this->assertSame('comment', Lead::query()->value('source_type'));
    }

    public function test_parser_down_marks_scan_run_and_throws(): void
    {
        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'down'], 503),
        ]);

        try {
            app(GroupScanner::class)->scan($this->group, trigger: 'test');
            $this->fail('Expected ParserUnavailableException');
        } catch (ParserUnavailableException) {
            // expected
        }

        $run = ScanRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame(ScanRun::STATUS_PARSER_DOWN, $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertDatabaseCount('vk_posts', 0);
    }

    public function test_invalid_group_url_fails_scan_run(): void
    {
        $this->group->forceFill(['url' => 'https://example.com/not-vk'])->save();

        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        try {
            app(GroupScanner::class)->scan($this->group, trigger: 'test');
            $this->fail('Expected invalid URL exception');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $run = ScanRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame(ScanRun::STATUS_FAILED, $run->status);
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     */
    private function fakeParserHealthy(array $posts): void
    {
        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'ok'], 200),
            'http://parser.test/scrape/group' => Http::response([
                'success' => true,
                'data' => $posts,
            ], 200),
            'http://parser.test/scrape/comments' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
        ]);
    }
}
