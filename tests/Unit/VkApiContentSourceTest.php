<?php

namespace Tests\Unit;

use App\Models\VkPost;
use App\Modules\VkApi\VkApiContentSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkApiContentSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vk.api_token' => 'test-vk-token',
            'services.vk.api_version' => '5.199',
            'services.vk.api_url' => 'https://api.vk.test/method',
        ]);
    }

    public function test_normalizes_official_api_wall_posts(): void
    {
        Http::fake([
            'https://api.vk.test/method/utils.resolveScreenName*' => Http::response([
                'response' => ['object_id' => 42, 'type' => 'group'],
            ]),
            'https://api.vk.test/method/wall.get*' => Http::response([
                'response' => ['items' => [[
                    'id' => 7,
                    'owner_id' => -42,
                    'from_id' => -42,
                    'text' => 'Нужен ремонт',
                    'date' => 1_784_880_000,
                ]]],
            ]),
        ]);

        $posts = app(VkApiContentSource::class)->fetchGroup('https://vk.com/test_group', 6);

        $this->assertSame('-42_7', $posts[0]['vk_post_id']);
        $this->assertSame('Нужен ремонт', $posts[0]['text']);
        $this->assertSame('https://vk.com/wall-42_7', $posts[0]['url']);
        $this->assertSame(-42, $posts[0]['author_id']);
        $this->assertSame('group', $posts[0]['author_type']);
        $this->assertNotEmpty($posts[0]['posted_at']);
    }

    public function test_normalizes_top_level_and_thread_comments(): void
    {
        Http::fake([
            'https://api.vk.test/method/wall.getComments*' => Http::response([
                'response' => ['items' => [[
                    'id' => 10,
                    'from_id' => 20,
                    'text' => 'Первый комментарий',
                    'date' => 1_784_880_000,
                    'thread' => ['items' => [[
                        'id' => 11,
                        'from_id' => 21,
                        'text' => 'Ответ',
                        'date' => 1_784_880_100,
                    ]], 'count' => 1],
                ]]],
            ]),
        ]);

        $post = new VkPost(['vk_post_id' => '-42_7', 'url' => 'https://vk.com/wall-42_7']);
        $comments = app(VkApiContentSource::class)->fetchComments($post);

        $this->assertSame(2, count($comments));
        $this->assertSame(10, $comments[0]['vk_comment_id']);
        $this->assertNull($comments[0]['parent_comment_id']);
        $this->assertSame(11, $comments[1]['vk_comment_id']);
        $this->assertSame(10, $comments[1]['parent_comment_id']);
    }

    public function test_loads_all_thread_pages_and_keeps_nested_parent(): void
    {
        Http::fake(function ($request) {
            $query = $request->data();

            if (! isset($query['start_comment_id'])) {
                return Http::response(['response' => ['items' => [[
                    'id' => 10,
                    'from_id' => 20,
                    'text' => 'Корневой',
                    'date' => 1_784_880_000,
                    'thread' => [
                        'count' => 11,
                        'items' => [[
                            'id' => 11,
                            'from_id' => 21,
                            'text' => 'Первый ответ',
                            'date' => 1_784_880_100,
                        ]],
                    ],
                ]]]]);
            }

            return Http::response(['response' => ['items' => [[
                'id' => 11,
                'from_id' => 21,
                'text' => 'Первый ответ',
                'date' => 1_784_880_100,
            ], [
                'id' => 12,
                'from_id' => 22,
                'text' => 'Вложенный ответ',
                'parents_stack' => [10, 11],
                'date' => 1_784_880_200,
            ]]]]);
        });

        $post = new VkPost(['vk_post_id' => '-42_7', 'url' => 'https://vk.com/wall-42_7']);
        $comments = app(VkApiContentSource::class)->fetchComments($post);

        $this->assertCount(3, $comments);
        $this->assertSame(11, $comments[2]['parent_comment_id']);
    }
}
