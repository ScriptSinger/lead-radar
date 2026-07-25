<?php

namespace Tests\Unit;

use App\Models\VkComment;
use App\Models\VkGroup;
use App\Models\VkPost;
use App\Services\Vk\CommentTreeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTreeResolverTest extends TestCase
{
    use RefreshDatabase;

    private CommentTreeResolver $resolver;

    private VkPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new CommentTreeResolver;

        $group = VkGroup::query()->create([
            'name' => 'Test',
            'url' => 'https://vk.com/club1',
            'active' => true,
        ]);

        $this->post = VkPost::query()->create([
            'group_id' => $group->id,
            'vk_post_id' => '-1_1',
            'text' => 'Post',
            'url' => 'https://vk.com/wall-1_1',
            'posted_at' => now(),
        ]);
    }

    public function test_true_root_and_nested_reply(): void
    {
        $root = $this->comment(100, null, 'root text');
        $reply = $this->comment(101, 100, 'reply text');

        $stats = $this->resolver->resolveForPost($this->post);

        $root->refresh();
        $reply->refresh();

        $this->assertTrue($root->isTrueRoot());
        $this->assertFalse($root->isOrphan());
        $this->assertTrue($reply->isNested());
        $this->assertFalse($reply->isOrphan());
        $this->assertSame($root->id, $reply->parent_id);
        $this->assertSame(1, $reply->depth);
        $this->assertSame(0, $stats['orphans']);
        $this->assertSame(1, $stats['roots']);
        $this->assertSame(1, $stats['nested']);
    }

    public function test_missing_parent_is_orphan_false_root(): void
    {
        // Reply to vk 999 which was never scraped
        $orphan = $this->comment(200, 999, 'orphan reply');

        $stats = $this->resolver->resolveForPost($this->post);

        $orphan->refresh();

        $this->assertTrue($orphan->isOrphan());
        $this->assertFalse($orphan->isTrueRoot());
        $this->assertNull($orphan->parent_id);
        $this->assertSame(0, (int) $orphan->depth);
        $this->assertSame(1, $stats['orphans']);
        $this->assertSame(0, $stats['roots']);
        $this->assertSame(0, $stats['nested']);
    }

    private function comment(int $vkId, ?int $parentVk, string $text): VkComment
    {
        return VkComment::query()->create([
            'post_id' => $this->post->id,
            'vk_comment_id' => $vkId,
            'parent_vk_comment_id' => $parentVk,
            'parent_id' => null,
            'thread_root_id' => null,
            'depth' => 0,
            'text' => $text,
            'url' => 'https://vk.com/wall-1_1?reply='.$vkId,
            'posted_at' => now(),
        ]);
    }
}
