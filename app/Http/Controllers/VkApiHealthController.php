<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class VkApiHealthController extends Controller
{
    /**
     * Verifies that the configured server-side service token can call VK API.
     * The response never includes the access token or VK user data.
     */
    public function __invoke(): JsonResponse
    {
        $token = (string) config('services.vk.api_token');

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'message' => 'VK_API_TOKEN is not configured.',
            ], 503);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(rtrim((string) config('services.vk.api_url'), '/').'/users.get', [
                    'access_token' => $token,
                    'v' => config('services.vk.api_version'),
                ]);
        } catch (Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'VK API is unreachable.',
            ], 503);
        }

        $payload = $response->json();
        $error = is_array($payload) ? ($payload['error'] ?? null) : null;

        if (! $response->successful() || $error !== null) {
            return response()->json([
                'ok' => false,
                'message' => is_array($error)
                    ? ($error['error_msg'] ?? 'VK API rejected the request.')
                    : 'VK API returned an unexpected response.',
                'vk_error_code' => is_array($error) ? ($error['error_code'] ?? null) : null,
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => 'VK API is available and the token is valid.',
            'api_version' => config('services.vk.api_version'),
        ]);
    }
}
