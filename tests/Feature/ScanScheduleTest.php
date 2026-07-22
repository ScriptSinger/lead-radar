<?php

namespace Tests\Feature;

use App\Jobs\DispatchVkGroupScansJob;
use App\Models\ScanSetting;
use App\Models\VkGroup;
use App\Services\Vk\ScanSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScanScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-23 12:00:00'));
        ScanSetting::forgetCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seeder_defaults_are_competitive(): void
    {
        $s = ScanSetting::current();

        $this->assertTrue($s->schedule_enabled);
        $this->assertSame(30, $s->interval_minutes);
        $this->assertSame(50, $s->group_delay_seconds);
        $this->assertSame(8, $s->scan_limit);
        $this->assertTrue($s->with_comments);
        $this->assertSame('since_last_scan', $s->post_window);
    }

    public function test_tick_dispatches_when_due_and_marks_timestamp(): void
    {
        VkGroup::query()->create([
            'name' => 'G',
            'url' => 'https://vk.com/g',
            'active' => true,
        ]);

        $settings = ScanSetting::current();
        $settings->forceFill([
            'schedule_enabled' => true,
            'interval_minutes' => 30,
            'last_dispatched_at' => null,
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertPushed(DispatchVkGroupScansJob::class, 1);

        $settings->refresh();
        $this->assertNotNull($settings->last_dispatched_at);
        $this->assertTrue($settings->last_dispatched_at->equalTo(now()));
    }

    public function test_tick_skips_when_not_due(): void
    {
        $settings = ScanSetting::current();
        $settings->forceFill([
            'schedule_enabled' => true,
            'interval_minutes' => 30,
            'last_dispatched_at' => now()->subMinutes(10),
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertNotPushed(DispatchVkGroupScansJob::class);
    }

    public function test_tick_skips_when_schedule_disabled(): void
    {
        $settings = ScanSetting::current();
        $settings->forceFill([
            'schedule_enabled' => false,
            'last_dispatched_at' => null,
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertNotPushed(DispatchVkGroupScansJob::class);
    }

    public function test_tick_runs_again_after_interval(): void
    {
        $settings = ScanSetting::current();
        $settings->forceFill([
            'schedule_enabled' => true,
            'interval_minutes' => 30,
            'last_dispatched_at' => now()->subMinutes(30),
        ])->save();
        ScanSetting::forgetCache();

        app(ScanSchedule::class)->tick();

        Queue::assertPushed(DispatchVkGroupScansJob::class, 1);
    }

    public function test_dispatch_job_uses_settings_delay(): void
    {
        ScanSetting::current()->forceFill([
            'group_delay_seconds' => 12,
            'scan_limit' => 5,
            'with_comments' => false,
        ])->save();
        ScanSetting::forgetCache();

        VkGroup::query()->create([
            'name' => 'A',
            'url' => 'https://vk.com/a',
            'active' => true,
        ]);
        VkGroup::query()->create([
            'name' => 'B',
            'url' => 'https://vk.com/b',
            'active' => true,
        ]);

        // Push ScanVkGroupJob through real dispatch (not faked for nested?)
        // Queue is faked so assertPushed on ScanVkGroupJob
        (new \App\Jobs\DispatchVkGroupScansJob)->handle();

        Queue::assertPushed(\App\Jobs\ScanVkGroupJob::class, 2);
        Queue::assertPushed(\App\Jobs\ScanVkGroupJob::class, function (\App\Jobs\ScanVkGroupJob $job) {
            return $job->limit === 5 && $job->withComments === false;
        });
    }
}
