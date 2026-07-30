<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkApiHealthControllerTest extends TestCase
{
    public function test_reports_missing_token_without_requesting_vk(): void
    {
        config(['services.vk.api_token' => null]);

        $this->getJson('/api/vk/health')
            ->assertServiceUnavailable()
            ->assertJson([
                'ok' => false,
                'message' => 'VK_API_TOKEN is not configured.',
            ]);

        Http::assertNothingSent();
    }

    public function test_reports_success_without_exposing_token(): void
    {
        config([
            'services.vk.api_token' => 'test-vk-token',
            'services.vk.api_version' => '5.199',
            'services.vk.api_url' => 'https://api.vk.test/method',
        ]);

        Http::fake([
            'https://api.vk.test/method/users.get*' => Http::response(['response' => [['id' => 1]]]),
        ]);

        $this->getJson('/api/vk/health')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'api_version' => '5.199',
            ])
            ->assertJsonMissing(['test-vk-token']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.vk.test/method/users.get?access_token=test-vk-token&v=5.199';
        });
    }

    public function test_returns_vk_error_without_token(): void
    {
        config(['services.vk.api_token' => 'test-vk-token']);

        Http::fake([
            'https://api.vk.com/method/users.get*' => Http::response([
                'error' => [
                    'error_code' => 5,
                    'error_msg' => 'User authorization failed',
                ],
            ]),
        ]);

        $this->getJson('/api/vk/health')
            ->assertStatus(502)
            ->assertJson([
                'ok' => false,
                'vk_error_code' => 5,
            ])
            ->assertJsonMissing(['test-vk-token']);
    }
}
