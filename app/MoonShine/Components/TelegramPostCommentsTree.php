<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use App\Models\TelegramComment;
use App\Models\TelegramPost;
use App\MoonShine\Resources\TelegramCommentResource;
use MoonShine\AssetManager\Css;
use MoonShine\UI\Components\MoonShineComponent;

final class TelegramPostCommentsTree extends MoonShineComponent
{
    protected string $view = 'moonshine.components.post-comments-tree';

    public function __construct(private readonly TelegramPost $post)
    {
        parent::__construct();
    }

    protected function assets(): array
    {
        return [Css::make('vendor/moonshine-tree/tree.css')];
    }

    protected function viewData(): array
    {
        $comments = $this->post->comments()->with(['post.channel'])->orderBy('posted_at')->get();
        $items = [];
        foreach ($comments as $comment) {
            $items[$comment->parent_id ?? 0][$comment->id] = $comment;
        }
        $resource = app(TelegramCommentResource::class);

        return ['items' => $items, 'hasRoots' => ! empty($items[0]), 'resource' => $resource, 'route' => $resource->getRoute('sortable'), 'buttons' => function (TelegramComment $item) use ($resource) {
            $resource->setItem($item);

            return $resource->getIndexPage()?->getButtons()->fill($resource->getCastedData())?->withoutBulk();
        }];
    }
}
