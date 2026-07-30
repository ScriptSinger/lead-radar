<?php

namespace App\Contracts;

use App\Models\VkPost;

/**
 * Normalized source for VK wall content.
 *
 * Sources may use the official VK API or the legacy page parser, while the
 * scanner stores exactly the same normalized post and comment payloads.
 */
interface VkContentSource
{
    public function health(): bool;

    /** @return list<array<string, mixed>> */
    public function fetchGroup(string $url, int $limit): array;

    /** @return list<array<string, mixed>> */
    public function fetchComments(VkPost $post): array;
}
