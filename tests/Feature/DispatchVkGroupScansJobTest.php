<?php

namespace Tests\Feature;

use App\Jobs\DispatchVkGroupScansJob;
use App\Jobs\ScanVkGroupJob;
use App\Models\VkGroup;
use App\Models\ScanRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchVkGroupScansJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_active_groups_with_valid_urls_and_skips_invalid(): void
    {
        Queue::fake();
        \App\Models\ScanSetting::forgetCache();

        \App\Models\ScanSetting::current()->forceFill([
            'group_delay_seconds' => 10,
            'scan_limit' => 6,
        ])->save();
        \App\Models\ScanSetting::forgetCache();

        $good = VkGroup::query()->create([
            'name' => 'Good',
            'url' => 'https://vk.com/good',
            'active' => true,
        ]);
        VkGroup::query()->create([
            'name' => 'Bad url',
            'url' => 'https://example.com/x',
            'active' => true,
        ]);
        VkGroup::query()->create([
            'name' => 'Inactive',
            'url' => 'https://vk.com/inactive',
            'active' => false,
        ]);

        (new DispatchVkGroupScansJob(limit: 4, withComments: true, trigger: 'test'))->handle();

        Queue::assertPushed(ScanVkGroupJob::class, 1);
        Queue::assertPushed(ScanVkGroupJob::class, function (ScanVkGroupJob $job) use ($good) {
            return $job->groupId === $good->id
                && $job->limit === 4
                && $job->withComments === true
                && $job->trigger === 'test';
        });
    }

    public function test_recent_slow_scans_raise_the_next_wave_delay(): void
    {
        config(['services.vk.adaptive_group_delay' => true]);
        $group = VkGroup::query()->create([
            'name' => 'Slow',
            'url' => 'https://vk.com/slow',
            'active' => true,
        ]);
        $settings = \App\Models\ScanSetting::current();
        $settings->forceFill(['group_delay_seconds' => 10])->save();

        ScanRun::query()->create([
            'group_id' => $group->id,
            'status' => ScanRun::STATUS_SUCCESS,
            'duration_ms' => 121000,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->assertSame(121, $settings->fresh()->effectiveGroupDelaySeconds());
    }
}
