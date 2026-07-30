<?php

namespace App\Services\Vk;

use App\Contracts\VkContentSource;
use App\Models\VkPost;

/** Legacy source backed by the isolated Playwright parser service. */
class ParserContentSource implements VkContentSource
{
    public function __construct(private readonly ParserClient $parser) {}

    public function health(): bool
    {
        return $this->parser->health();
    }

    public function fetchGroup(string $url, int $limit): array
    {
        return $this->parser->scrapeGroup($url, $limit);
    }

    public function fetchComments(VkPost $post): array
    {
        return $this->parser->scrapeComments($post->url);
    }
}
