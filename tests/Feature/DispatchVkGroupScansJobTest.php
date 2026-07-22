<?php

namespace Tests\Feature;

use App\Jobs\DispatchVkGroupScansJob;
use App\Jobs\ScanVkGroupJob;
use App\Models\VkGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchVkGroupScansJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_active_groups_with_valid_urls_and_skips_invalid(): void
    {
        Queue::fake();

        config(['services.vk.scan_group_delay_seconds' => 10]);

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
}
