<?php

namespace Tests\Unit;

use App\Support\TelegramChannelUrl;
use PHPUnit\Framework\TestCase;

class TelegramChannelUrlTest extends TestCase
{
    public function test_normalizes_public_channel_links_and_usernames(): void
    {
        $this->assertSame('https://t.me/test_channel', TelegramChannelUrl::normalize('@Test_Channel'));
        $this->assertSame('https://t.me/test_channel', TelegramChannelUrl::normalize('https://t.me/Test_Channel/'));
        $this->assertSame('https://t.me/test_channel', TelegramChannelUrl::normalize('https://t.me/s/test_channel'));
    }

    public function test_rejects_private_invites_posts_and_non_telegram_urls(): void
    {
        $this->assertFalse(TelegramChannelUrl::isValid('https://t.me/+privateInvite'));
        $this->assertFalse(TelegramChannelUrl::isValid('https://t.me/test_channel/123'));
        $this->assertFalse(TelegramChannelUrl::isValid('https://example.com/test_channel'));
        $this->assertFalse(TelegramChannelUrl::isValid('@bad'));
    }
}
