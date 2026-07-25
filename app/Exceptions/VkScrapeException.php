<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Structured failure from the VK parser microservice.
 *
 * Codes (parser): VK_CAPTCHA | VK_LOGIN | VK_BLOCKED | EMPTY_WALL | PARSE_ERROR
 */
class VkScrapeException extends RuntimeException
{
    public const CODE_CAPTCHA = 'VK_CAPTCHA';

    public const CODE_LOGIN = 'VK_LOGIN';

    public const CODE_BLOCKED = 'VK_BLOCKED';

    public const CODE_EMPTY_WALL = 'EMPTY_WALL';

    public const CODE_PARSE = 'PARSE_ERROR';

    /**
     * @param  array<string, mixed>|null  $diagnostics
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = self::CODE_PARSE,
        public readonly ?array $diagnostics = null,
        public readonly int $httpStatus = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isCaptcha(): bool
    {
        return $this->errorCode === self::CODE_CAPTCHA;
    }

    public function isBlocking(): bool
    {
        return in_array($this->errorCode, [
            self::CODE_CAPTCHA,
            self::CODE_LOGIN,
            self::CODE_BLOCKED,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'code' => $this->errorCode,
            'http_status' => $this->httpStatus,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
