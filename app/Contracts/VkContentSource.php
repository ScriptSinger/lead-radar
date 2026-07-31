<?php

namespace App\Contracts;

use App\Models\VkPost;

/**
 * Normalized source for VK wall content.
 *
 * The official VK API is normalized for storage by the scanner.
 */
interface VkContentSource
{
    public function health(): bool;

    /** @return list<array<string, mixed>> */
    public function fetchGroup(string $url, int $limit): array;

    /** @return list<array<string, mixed>> */
    public function fetchComments(VkPost $post): array;
}
