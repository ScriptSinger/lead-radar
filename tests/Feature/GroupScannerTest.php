<?php

namespace Tests\Feature;

use App\Exceptions\ParserUnavailableException;
use App\Models\Keyword;
use App\Models\Lead;
use App\Models\ScanRun;
use App\Models\VkGroup;
use App\Models\VkPost;
use App\Services\Vk\GroupScanner;
use App\Support\PostWindow;
use Carbon\Carbon;
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
        Carbon::setTestNow(Carbon::parse('2026-07-23 14:00:00'));
        \App\Models\ScanSetting::forgetCache();
        config([
            'services.telegram.notify_enabled' => false,
            'services.parser.url' => 'http://parser.test',
            'services.parser.timeout' => 5,
            'services.vk.post_window' => PostWindow::MODE_ALL,
        ]);
        \App\Models\ScanSetting::current()->forceFill([
            'post_window' => PostWindow::MODE_ALL,
        ])->save();
        \App\Models\ScanSetting::forgetCache();

        $this->group = VkGroup::query()->create([
            'name' => 'Scan Fixture',
            'url' => 'https://vk.com/fixture_group',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scan_upserts_posts_matches_leads_and_writes_scan_run(): void
    {
        Keyword::query()->create(['word' => 'холодильник', 'type' => 'both']);

        $this->fakeParserHealthy([
            [
                'vk_post_id' => '-42_100',
                'text' => 'Нужен ремонт холодильника',
                'url' => 'https://vk.com/wall-42_100',
                'posted_at' => '2026-07-23T10:00:00+00:00',
                'author_id' => 9,
            ],
            [
                'vk_post_id' => '-42_101',
                'text' => 'Продаю стол',
                'url' => 'https://vk.com/wall-42_101',
                'posted_at' => '2026-07-23T11:00:00+00:00',
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

        // Second scan: upsert updates existing posts; same window posts rematch → update
        $stats2 = app(GroupScanner::class)->scan($this->group, limit: 6, withComments: false, trigger: 'test');
        $this->assertSame(0, $stats2['posts_created']);
        $this->assertSame(2, $stats2['posts_updated']);
        $this->assertSame(0, $stats2['leads_created']);
        $this->assertSame(1, $stats2['leads_updated']);
        $this->assertDatabaseCount('vk_posts', 2);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame(2, ScanRun::query()->where('status', ScanRun::STATUS_SUCCESS)->count());
    }

    public function test_today_window_skips_older_posts_for_match_and_comments(): void
    {
        config(['services.vk.post_window' => PostWindow::MODE_TODAY]);
        Keyword::query()->create(['word' => 'холодильник', 'type' => 'both']);
        Keyword::query()->create(['word' => 'стиральная', 'type' => 'comment']);

        Http::fake([
            'http://parser.test/health' => Http::response(['status' => 'ok'], 200),
            'http://parser.test/scrape/group' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'vk_post_id' => '-1_old',
                        'text' => 'Вчера: ремонт холодильника',
                        'url' => 'https://vk.com/wall-1_old',
                        'posted_at' => '2026-07-22T18:00:00+00:00',
                        'author_id' => 1,
                    ],
                    [
                        'vk_post_id' => '-1_new',
                        'text' => 'Сегодня без ключа',
                        'url' => 'https://vk.com/wall-1_new',
                        'posted_at' => '2026-07-23T09:00:00+00:00',
                        'author_id' => 1,
                    ],
                ],
            ], 200),
            'http://parser.test/scrape/comments' => Http::response([
                'success' => true,
                'data' => [[
                    'vk_comment_id' => '55',
                    'parent_comment_id' => null,
                    'text' => 'Стиральная течёт',
                    'url' => 'https://vk.com/wall-1_new?reply=55',
                    'posted_at' => '2026-07-23T10:00:00+00:00',
                    'author_id' => 2,
                ]],
            ], 200),
        ]);

        $stats = app(GroupScanner::class)->scan(
            $this->group,
            limit: 10,
            withComments: true,
            trigger: 'test',
            postWindow: PostWindow::MODE_TODAY,
        );

        $this->assertSame(2, $stats['posts_fetched']);
        $this->assertSame(2, $stats['posts_created']);
        $this->assertSame(1, $stats['posts_in_window']);
        $this->assertSame(1, $stats['posts_outside_window']);
        // only today's post got comments
        $this->assertSame(1, $stats['comments_fetched']);
        $this->assertDatabaseCount('vk_comments', 1);
        // old post had keyword but outside window → no post-lead; comment lead only
        $this->assertSame(1, $stats['leads_created']);
        $this->assertSame('comment', Lead::query()->value('source_type'));
    }

    public function test_since_last_scan_window_uses_last_scan_at(): void
    {
        config(['services.vk.post_window' => PostWindow::MODE_SINCE_LAST_SCAN]);
        $this->group->forceFill(['last_scan_at' => Carbon::parse('2026-07-23 12:00:00')])->save();

        Keyword::query()->create(['word' => 'мастер', 'type' => 'both']);

        $this->fakeParserHealthy([
            [
                'vk_post_id' => '-9_1',
                'text' => 'Нужен мастер до полудня',
                'url' => 'https://vk.com/wall-9_1',
                'posted_at' => '2026-07-23T11:00:00+00:00',
                'author_id' => 1,
            ],
            [
                'vk_post_id' => '-9_2',
                'text' => 'Нужен мастер после полудня',
                'url' => 'https://vk.com/wall-9_2',
                'posted_at' => '2026-07-23T13:00:00+00:00',
                'author_id' => 1,
            ],
        ]);

        $stats = app(GroupScanner::class)->scan(
            $this->group,
            withComments: false,
            trigger: 'test',
            postWindow: PostWindow::MODE_SINCE_LAST_SCAN,
        );

        $this->assertSame(1, $stats['posts_in_window']);
        $this->assertSame(1, $stats['posts_outside_window']);
        $this->assertSame(1, $stats['leads_created']);
        $this->assertDatabaseCount('leads', 1);
        $this->assertStringContainsString('после полудня', (string) Lead::query()->value('text'));
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
                    'posted_at' => '2026-07-23T12:00:00+00:00',
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
                    'posted_at' => '2026-07-23T13:00:00+00:00',
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
