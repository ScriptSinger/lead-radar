<?php
namespace Tests\Unit;
use App\Modules\Telegram\TelegramContentSource;
use App\Services\Telegram\TelegramMtprotoClient;
use Mockery;
use PHPUnit\Framework\TestCase;
class TelegramContentSourceTest extends TestCase {
 public function test_normalizes_a_channel_message(): void {
  $source=new TelegramContentSource(Mockery::mock(TelegramMtprotoClient::class));
  $post=$source->normalizeMessage(['_'=>'message','id'=>42,'date'=>1700000000,'message'=>'Нужен ремонт','from_id'=>['user_id'=>99],'media'=>['_'=>'messageMediaPhoto']], 'test_channel');
  $this->assertSame(42,$post['telegram_message_id']); $this->assertSame('https://t.me/test_channel/42',$post['url']); $this->assertSame(99,$post['author_telegram_id']); $this->assertTrue($post['has_media']);
 }
}
