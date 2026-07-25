<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\Models\VkPost;
use App\MoonShine\Components\PostCommentsTree;
use App\MoonShine\Resources\VkPost\VkPostResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\LineBreak;
use Throwable;

/**
 * Fields come from VkPostResource::detailFields().
 * Nested comments tree is appended in bottomLayer (scoped to this post).
 *
 * @extends DetailPage<VkPostResource>
 */
class VkPostDetailPage extends DetailPage
{
    /**
     * @return list<\MoonShine\Contracts\UI\ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        $components = parent::bottomLayer();

        if (! $this->isItemExists()) {
            return $components;
        }

        /** @var VkPost|null $post */
        $post = $this->getItem();

        if (! $post instanceof VkPost) {
            return $components;
        }

        $components[] = LineBreak::make();
        $components[] = Box::make('Comments', [
            PostCommentsTree::make($post),
        ]);

        return $components;
    }
}
