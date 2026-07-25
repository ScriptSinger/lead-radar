<?php

namespace Tests\Feature;

use App\Jobs\DispatchVkGroupScansJob;
use App\Models\ScanSetting;
use App\Models\VkGroup;
use App\Services\Vk\CaptchaPauseGuard;
use App\Services\Vk\ScanSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaptchaPauseGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-07-25 12:00:00'));
        ScanSetting::forgetCache();
        config([
            'services.vk.captcha_pause_threshold' => 3,
            'services.vk.captcha_pause_minutes' => 60,
            'services.telegram.notify_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_three_blocking_failures_pause_schedule(): void
    {
        $guard = app(CaptchaPauseGuard::class);

        $guard->recordBlockingFailure('VK_CAPTCHA', 1);
        $this->assertFalse(ScanSetting::current()->isCaptchaPaused());

        $guard->recordBlockingFailure('VK_CAPTCHA', 2);
        $this->assertFalse(ScanSetting::current()->isCaptchaPaused());

        $result = $guard->recordBlockingFailure('VK_CAPTCHA', 3);
        $this->assertTrue($result['paused']);
        $this->assertTrue(ScanSetting::current()->isCaptchaPaused());
        $this->assertNotNull(ScanSetting::current()->paused_until);
        $this->assertStringContainsString('VK_CAPTCHA', (string) ScanSetting::current()->pause_reason);
    }

    public function test_success_resets_streak(): void
    {
        $guard = app(CaptchaPauseGuard::class);
        $guard->recordBlockingFailure('VK_CAPTCHA', 1);
        $guard->recordBlockingFailure('VK_CAPTCHA', 2);
        $this->assertSame(2, $guard->streak());

        $guard->recordSuccess();
        $this->assertSame(0, $guard->streak());

        $guard->recordBlockingFailure('VK_CAPTCHA', 3);
        $this->assertFalse(ScanSetting::current()->isCaptchaPaused());
        $this->assertSame(1, $guard->streak());
    }

    public function test_tick_skips_while_paused(): void
    {
        VkGroup::query()->create([
            'name' => 'G',
            'url' => 'https://vk.com/g',
            'active' => true,
        ]);

        $s = ScanSetting::current();
        $s->forceFill([
            'schedule_enabled' => true,
            'last_dispatched_at' => null,
            'paused_until' => now()->addHour(),
            'pause_reason' => 'test',
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertNotPushed(DispatchVkGroupScansJob::class);
    }

    public function test_tick_clears_expired_pause_and_can_dispatch(): void
    {
        VkGroup::query()->create([
            'name' => 'G',
            'url' => 'https://vk.com/g',
            'active' => true,
        ]);

        $s = ScanSetting::current();
        $s->forceFill([
            'schedule_enabled' => true,
            'last_dispatched_at' => null,
            'paused_until' => now()->subMinute(),
            'pause_reason' => 'expired',
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertPushed(DispatchVkGroupScansJob::class, 1);
        $this->assertNull(ScanSetting::current()->paused_until);
        $this->assertNull(ScanSetting::current()->pause_reason);
    }
}
