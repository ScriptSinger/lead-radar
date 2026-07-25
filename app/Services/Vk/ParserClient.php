<?php

namespace App\Services\Vk;

use App\Exceptions\ParserUnavailableException;
use App\Exceptions\VkScrapeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client for the Playwright parser microservice.
 *
 * Contract: parser/README.md
 * Errors: { success:false, error, code?, diagnostics? }
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
            throw new ParserUnavailableException(
                "Parser unreachable at {$this->baseUrl()}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $body = $response->json();
        $errorText = is_array($body)
            ? (string) ($body['error'] ?? $response->body())
            : $response->body();
        $code = is_array($body) && isset($body['code'])
            ? (string) $body['code']
            : null;
        /** @var array<string, mixed>|null $diagnostics */
        $diagnostics = is_array($body) && isset($body['diagnostics']) && is_array($body['diagnostics'])
            ? $body['diagnostics']
            : null;

        if ($response->failed() || (is_array($body) && ($body['success'] ?? null) === false)) {
            $this->throwStructuredFailure(
                path: $path,
                payload: $payload,
                httpStatus: $response->status(),
                errorText: $errorText !== '' ? $errorText : 'parser failure',
                code: $code,
                diagnostics: $diagnostics,
            );
        }

        if (! is_array($body) || ($body['success'] ?? null) !== true) {
            throw new RuntimeException(
                'Parser returned unexpected payload: '.(is_string($errorText) ? $errorText : 'unknown'),
            );
        }

        $data = $body['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('Parser response missing data array');
        }

        return array_values($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $diagnostics
     */
    private function throwStructuredFailure(
        string $path,
        array $payload,
        int $httpStatus,
        string $errorText,
        ?string $code,
        ?array $diagnostics,
    ): never {
        $resolvedCode = $code ?: $this->inferCodeFromMessage($errorText);

        Log::error('vk.parser.request_failed', [
            'path' => $path,
            'url' => $payload['url'] ?? null,
            'http_status' => $httpStatus,
            'code' => $resolvedCode,
            'error' => $errorText,
            'diagnostics' => $diagnostics,
            'verdict' => $diagnostics['verdict'] ?? null,
            'confidence' => $diagnostics['confidence'] ?? null,
            'signals' => isset($diagnostics['signals']) && is_array($diagnostics['signals'])
                ? array_map(
                    static fn ($s) => is_array($s)
                        ? ($s['id'] ?? null)
                        : null,
                    $diagnostics['signals'],
                )
                : null,
        ]);

        throw new VkScrapeException(
            message: $errorText,
            errorCode: $resolvedCode,
            diagnostics: $diagnostics,
            httpStatus: $httpStatus,
        );
    }

    private function inferCodeFromMessage(string $message): string
    {
        $m = mb_strtolower($message);

        if (str_contains($m, 'captcha') || str_contains($m, 'challenge') || str_contains($m, 'не робот') || str_contains($m, 'robot')) {
            return VkScrapeException::CODE_CAPTCHA;
        }
        if (str_contains($m, 'login') || str_contains($m, 'войти')) {
            return VkScrapeException::CODE_LOGIN;
        }
        if (str_contains($m, 'blocked') || str_contains($m, 'access denied')) {
            return VkScrapeException::CODE_BLOCKED;
        }

        return VkScrapeException::CODE_PARSE;
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
