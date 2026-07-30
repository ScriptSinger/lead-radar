<?php

namespace App\Modules\VkApi;

use RuntimeException;
use Throwable;

class VkApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $vkErrorCode = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
