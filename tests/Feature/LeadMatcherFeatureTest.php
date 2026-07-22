<?php

namespace Tests\Feature;

use App\Models\Keyword;
use App\Models\Lead;
use App\Models\VkComment;
use App\Models\VkGroup;
use App\Models\VkPost;
use App\Services\Vk\LeadMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadMatcherFeatureTest extends TestCase
{
    use RefreshDatabase;

    private LeadMatcher $matcher;

    private VkGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['services.telegram.notify_enabled' => false]);

        $this->matcher = app(LeadMatcher::class);
        $this->group = VkGroup::query()->create([
            'name' => 'Test Group',
            'url' => 'https://vk.com/test_group',
            'active' => true,
        ]);
    }

    public function test_creates_lead_from_post_keyword_match(): void
    {
        $keyword = Keyword::query()->create([
            'word' => 'холодильник',
            'type' => 'both',
        ]);

        $post = $this->makePost('Нужен ремонт холодильника срочно');

        $stats = $this->matcher->matchPost($post);

        $this->assertSame(1, $stats['created']);
        $this->assertSame(0, $stats['updated']);
        $this->assertDatabaseCount('leads', 1);

        $lead = Lead::query()->first();
        $this->assertNotNull($lead);
        $this->assertSame('post', $lead->source_type);
        $this->assertSame($post->id, $lead->post_id);
        $this->assertNull($lead->comment_id);
        $this->assertSame($keyword->id, $lead->keyword_id);
        $this->assertSame($this->group->id, $lead->group_id);
        $this->assertSame('new', $lead->status);
        $this->assertSame(LeadMatcher::SCORE_PER_HIT, $lead->score);
        $this->assertSame("p:{$post->id}:k:{$keyword->id}", $lead->dedupe_key);
    }

    public function test_dedupes_leads_on_rematch_and_preserves_status(): void
    {
        $keyword = Keyword::query()->create([
            'word' => 'стиральная',
            'type' => 'post',
        ]);

        $post = $this->makePost('Ищу мастера: стиральная машина не крутит');

        $first = $this->matcher->matchPost($post);
        $this->assertSame(1, $first['created']);

        $lead = Lead::query()->firstOrFail();
        $lead->forceFill(['status' => 'processed'])->save();

        $post->forceFill(['text' => 'Обновлено: стиральная всё ещё не работает'])->save();

        $second = $this->matcher->matchPost($post->fresh());
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertDatabaseCount('leads', 1);

        $lead->refresh();
        $this->assertSame('processed', $lead->status);
        $this->assertStringContainsString('Обновлено', $lead->text);
        $this->assertSame("p:{$post->id}:k:{$keyword->id}", $lead->dedupe_key);
    }

    public function test_creates_lead_from_comment_and_respects_keyword_type(): void
    {
        Keyword::query()->create(['word' => 'посудомойк', 'type' => 'comment']);
        Keyword::query()->create(['word' => 'вакансия', 'type' => 'post']);

        $post = $this->makePost('Объявление без ключа, вакансия здесь не должна матчиться как comment-only');
        $comment = $this->makeComment($post, 501, 'Нужен ремонт посудомойки, вакансия не то');

        // post-only keyword hits the post; comment-only keyword does not apply to posts
        $postStats = $this->matcher->matchPost($post);
        $this->assertSame(1, $postStats['created']);
        $this->assertSame('post', Lead::query()->value('source_type'));

        $commentStats = $this->matcher->matchComment($comment);
        $this->assertSame(1, $commentStats['created']);
        $this->assertDatabaseCount('leads', 2);

        $commentLead = Lead::query()->where('source_type', 'comment')->firstOrFail();
        $this->assertSame($comment->id, $commentLead->comment_id);
        $this->assertSame(
            "c:{$comment->id}:k:".Keyword::query()->where('word', 'посудомойк')->value('id'),
            $commentLead->dedupe_key,
        );
    }

    public function test_match_posts_batch_checks_comments(): void
    {
        Keyword::query()->create(['word' => 'мастер', 'type' => 'both']);

        $post = $this->makePost('Текст без совпадений');
        $this->makeComment($post, 77, 'Ищу мастера на выезд');

        $stats = $this->matcher->matchPosts(
            VkPost::query()->with('comments')->whereKey($post->id)->get(),
            withComments: true,
        );

        $this->assertSame(1, $stats['posts_checked']);
        $this->assertSame(1, $stats['comments_checked']);
        $this->assertSame(1, $stats['created']);
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('comment', Lead::query()->value('source_type'));
    }

    public function test_no_lead_without_keyword_hit(): void
    {
        Keyword::query()->create(['word' => 'холодильник', 'type' => 'both']);
        $post = $this->makePost('Продаю диван');

        $stats = $this->matcher->matchPost($post);

        $this->assertSame(0, $stats['created']);
        $this->assertGreaterThan(0, $stats['skipped']);
        $this->assertDatabaseCount('leads', 0);
    }

    private function makePost(string $text): VkPost
    {
        return VkPost::query()->create([
            'group_id' => $this->group->id,
            'vk_post_id' => '-100_'.random_int(1000, 999999),
            'text' => $text,
            'url' => 'https://vk.com/wall-100_1',
            'author_id' => 1,
            'posted_at' => now(),
        ]);
    }

    private function makeComment(VkPost $post, int $vkCommentId, string $text): VkComment
    {
        return VkComment::query()->create([
            'post_id' => $post->id,
            'vk_comment_id' => $vkCommentId,
            'parent_vk_comment_id' => null,
            'parent_id' => null,
            'thread_root_id' => null,
            'depth' => 0,
            'text' => $text,
            'author_id' => 2,
            'url' => $post->url.'?reply='.$vkCommentId,
            'posted_at' => now(),
        ]);
    }
}
