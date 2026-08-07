<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram\Pages;

use App\Models\TelegramPost;
use App\MoonShine\Components\TelegramPostCommentsTree;
use App\MoonShine\Resources\Telegram\TelegramPostResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Components\Layout\Box;

/** @extends DetailPage<TelegramPostResource> */
class TelegramPostDetailPage extends DetailPage
{
    protected function bottomLayer(): array
    {
        $items = parent::bottomLayer();
        $post = $this->getItem();
        if ($post instanceof TelegramPost) {
            $items[] = Box::make('Comments', [TelegramPostCommentsTree::make($post)]);
        }

        return $items;
    }
}
