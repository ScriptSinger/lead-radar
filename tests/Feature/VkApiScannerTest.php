<?php

namespace Tests\Feature;

use App\Contracts\VkContentSource;
use App\Models\Keyword;
use App\Models\ScanSetting;
use App\Models\VkGroup;
use App\Modules\VkApi\VkApiContentSource;
use App\Services\Vk\GroupScanner;
use App\Support\PostWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkApiScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_uses_official_api_source_for_posts_comments_and_leads(): void
    {
        config([
            'services.vk.content_source' => 'api',
            'services.vk.api_token' => 'test-vk-token',
            'services.vk.api_url' => 'https://api.vk.test/method',
            'services.vk.post_window' => PostWindow::MODE_ALL,
        ]);
        ScanSetting::current()->forceFill(['post_window' => PostWindow::MODE_ALL])->save();
        ScanSetting::forgetCache();
        Keyword::query()->create(['word' => 'сантехник', 'type' => 'both']);

        Http::fake([
            'https://api.vk.test/method/users.get*' => Http::response(['response' => [['id' => 1]]]),
            'https://api.vk.test/method/utils.resolveScreenName*' => Http::response([
                'response' => ['object_id' => 42, 'type' => 'group'],
            ]),
            'https://api.vk.test/method/wall.get?*' => Http::response([
                'response' => ['items' => [[
                    'id' => 7, 'owner_id' => -42, 'from_id' => -42,
                    'text' => 'Ищу сантехника', 'date' => now()->timestamp,
                ]]],
            ]),
            'https://api.vk.test/method/wall.getComments*' => Http::response([
                'response' => ['items' => [[
                    'id' => 10, 'from_id' => 2, 'text' => 'Обычный комментарий',
                    'date' => now()->timestamp,
                    'thread' => ['count' => 1, 'items' => [[
                        'id' => 11, 'from_id' => 3, 'text' => 'Тоже нужен сантехник',
                        'date' => now()->timestamp,
                    ]]],
                ]]],
            ]),
        ]);

        $group = VkGroup::query()->create([
            'name' => 'API fixture', 'url' => 'https://vk.com/api_fixture', 'active' => true,
        ]);

        $this->assertInstanceOf(VkApiContentSource::class, app(VkContentSource::class));

        $stats = app(GroupScanner::class)->scan($group, withComments: true, trigger: 'test');

        $this->assertSame(1, $stats['posts_fetched']);
        $this->assertSame(2, $stats['comments_fetched']);
        $this->assertSame(2, $stats['leads_created']);
        $this->assertDatabaseHas('vk_posts', ['vk_post_id' => '-42_7']);
        $this->assertDatabaseHas('vk_comments', [
            'vk_comment_id' => 11,
            'parent_vk_comment_id' => 10,
        ]);
    }
}
