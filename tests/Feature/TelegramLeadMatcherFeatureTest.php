<?php

namespace Tests\Feature;

use App\Jobs\NotifyNewLeadJob;
use App\Models\Keyword;
use App\Models\Lead;
use App\Models\TelegramChannel;
use App\Models\TelegramPost;
use App\Services\LeadMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramLeadMatcherFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_post_keyword_creates_one_telegram_lead_and_notification_job(): void
    {
        Queue::fake();
        config(['services.telegram.notify_enabled' => true]);
        $channel = TelegramChannel::query()->create(['name' => 'Test', 'url' => 'https://t.me/test_channel', 'username' => 'test_channel', 'active' => true]);
        $post = TelegramPost::query()->create(['channel_id' => $channel->id, 'telegram_message_id' => 7, 'text' => 'Нужен срочно ремонт', 'url' => 'https://t.me/test_channel/7', 'posted_at' => now()]);
        $keyword = Keyword::query()->create(['word' => 'ремонт', 'type' => 'post']);
        $stats = app(LeadMatcher::class)->matchTelegramPost($post);
        $this->assertSame(1, $stats['created']);
        $lead = Lead::query()->firstOrFail();
        $this->assertSame('telegram', $lead->platform);
        $this->assertSame($post->id, $lead->source_entity_id);
        $this->assertSame($channel->id, $lead->channel_or_group_id);
        $this->assertSame("telegram:p:{$post->id}:k:{$keyword->id}", $lead->dedupe_key);
        Queue::assertPushed(NotifyNewLeadJob::class);
    }
}
