<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use App\Models\VkComment;
use App\Models\VkPost;
use App\MoonShine\Resources\Vk\VkCommentResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Leeto\MoonShineTree\Resources\TreeResource;
use MoonShine\AssetManager\Css;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * Nested comments tree scoped to a single VK post (detail page embed).
 *
 * Reuses moonshine-tree markup + VkCommentResource title/badge helpers,
 * without loading all comments from the global resource index.
 *
 * @method static static make(?VkPost $post = null)
 */
final class PostCommentsTree extends MoonShineComponent
{
    protected string $view = 'moonshine.components.post-comments-tree';

    public function __construct(
        private readonly ?VkPost $post = null,
    ) {
        parent::__construct();
    }

    protected function assets(): array
    {
        return [
            Css::make('vendor/moonshine-tree/tree.css'),
        ];
    }

    /**
     * @return array<int|string, array<int|string, VkComment>>
     */
    protected function items(): array
    {
        if ($this->post === null) {
            return [];
        }

        /** @var TreeResource $resource */
        $resource = app(VkCommentResource::class);
        $treeKey = $resource->treeKey();

        $comments = $this->loadComments();
        $performed = [];

        foreach ($comments as $item) {
            $parent = $treeKey === null || $item->{$treeKey} === null
                ? 0
                : $item->{$treeKey};

            $performed[$parent][$item->getKey()] = $item;
        }

        // Orphans (parent missing in this post) surface as roots so nothing is hidden.
        $ids = $comments->modelKeys();
        foreach (array_keys($performed) as $parentId) {
            if ($parentId === 0 || $parentId === '0') {
                continue;
            }

            if (! in_array((int) $parentId, array_map('intval', $ids), true)) {
                foreach ($performed[$parentId] as $id => $item) {
                    $performed[0][$id] = $item;
                }
                unset($performed[$parentId]);
            }
        }

        return $performed;
    }

    /**
     * @return EloquentCollection<int, VkComment>
     */
    private function loadComments(): EloquentCollection
    {
        /** @var VkPost $post */
        $post = $this->post;

        return $post->comments()
            ->orderByRaw('COALESCE(thread_root_id, id) ASC')
            ->orderBy('depth', 'ASC')
            ->orderBy('posted_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    protected function viewData(): array
    {
        /** @var TreeResource&VkCommentResource $resource */
        $resource = app(VkCommentResource::class);
        $items = $this->items();
        $hasRoots = ! empty($items[0]);

        return [
            'items' => $items,
            'hasRoots' => $hasRoots,
            'resource' => $resource,
            'route' => $resource->getRoute('sortable'),
            'buttons' => function (VkComment $item) use ($resource) {
                $resource->setItem($item);

                return $resource->getIndexPage()
                    ?->getButtons()
                    ->fill($resource->getCastedData())
                    ?->withoutBulk();
            },
        ];
    }
}
