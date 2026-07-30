<?php

namespace App\Modules\VkApi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Thin client for official VK API methods. */
class VkApiClient
{
    /** @return array<string, mixed> */
    public function call(string $method, array $parameters = []): array
    {
        $token = (string) config('services.vk.api_token', '');
        if ($token === '') {
            throw new VkApiException('VK_API_TOKEN is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->get($this->url($method), [
                    ...$parameters,
                    'access_token' => $token,
                    'v' => config('services.vk.api_version', '5.199'),
                ]);
        } catch (ConnectionException $e) {
            throw new VkApiException('VK API is unreachable.', previous: $e);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new VkApiException('VK API returned an invalid response.', httpStatus: $response->status());
        }

        $error = $payload['error'] ?? null;
        if (! $response->successful() || is_array($error)) {
            throw new VkApiException(
                is_array($error) ? (string) ($error['error_msg'] ?? 'VK API rejected the request.') : 'VK API request failed.',
                is_array($error) && isset($error['error_code']) ? (int) $error['error_code'] : null,
                $response->status(),
            );
        }

        $result = $payload['response'] ?? null;
        if (! is_array($result)) {
            throw new VkApiException('VK API response is missing the response field.', httpStatus: $response->status());
        }

        return $result;
    }

    public function health(): bool
    {
        try {
            $this->call('users.get');

            return true;
        } catch (VkApiException) {
            return false;
        }
    }

    private function url(string $method): string
    {
        return rtrim((string) config('services.vk.api_url', 'https://api.vk.com/method'), '/')
            .'/'.ltrim($method, '/');
    }
}
