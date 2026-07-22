<?php

namespace App\Services\Vk;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the Playwright parser microservice.
 *
 * Contract: parser/README.md
 */
class ParserClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
    ) {}

    /**
     * @return list<array{
     *     vk_post_id: string,
     *     text: string,
     *     url: string,
     *     posted_at: ?string,
     *     author_id: int|string|null,
     *     posted_at_raw?: ?string
     * }>
     */
    public function scrapeGroup(string $url, int $limit = 6): array
    {
        return $this->request('/scrape/group', [
            'url' => $url,
            'limit' => $limit,
        ]);
    }

    /**
     * @return list<array{
     *     vk_comment_id: string,
     *     vk_post_id: ?string,
     *     parent_comment_id: ?string,
     *     text: string,
     *     url: string,
     *     posted_at: ?string,
     *     author_id: int|string|null,
     *     posted_at_raw?: ?string
     * }>
     */
    public function scrapeComments(string $url): array
    {
        return $this->request('/scrape/comments', [
            'url' => $url,
        ]);
    }

    public function health(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get($this->url('/health'));

            return $response->successful()
                && ($response->json('status') === 'ok');
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function request(string $path, array $payload): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($this->url($path), $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Parser unreachable at {$this->baseUrl()}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? $response->body();

            throw new RuntimeException(
                "Parser error HTTP {$response->status()}: {$error}",
            );
        }

        if ($response->json('success') !== true) {
            $error = $response->json('error') ?? 'unknown parser error';

            throw new RuntimeException("Parser returned failure: {$error}");
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Parser response missing data array');
        }

        return array_values($data);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return $this->baseUrl
            ?? (string) config('services.parser.url', 'http://parser:3000');
    }

    private function timeout(): int
    {
        return $this->timeout
            ?? (int) config('services.parser.timeout', 60);
    }
}
