<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram\Pages;

use App\MoonShine\Resources\Telegram\TelegramCommentResource;
use Leeto\MoonShineTree\View\Components\TreeComponent;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\Alert;

/** @extends IndexPage<TelegramCommentResource> */
class TelegramCommentIndexPage extends IndexPage
{
    public function getListComponent(bool $withoutFragment = false): ComponentContract
    {
        $resource = $this->getResource();
        $items = $resource?->getItems() ?? collect();
        $count = is_countable($items) ? count($items) : iterator_count($items);
        $content = $count > 0
            ? [TreeComponent::make($resource)]
            : [Alert::make()->content('No comments yet.')];

        if ($withoutFragment && count($content) === 1) {
            return $content[0];
        }

        return Fragment::make($content)->name('crud-list');
    }
}
