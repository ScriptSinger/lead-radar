<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\TelegramPost;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/** @extends ModelResource<TelegramPost> */
class TelegramPostResource extends ModelResource
{
    protected string $model = TelegramPost::class;

    protected string $title = 'Telegram Posts';

    protected string $column = 'display_name';

    protected array $with = ['channel'];

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('comments');
    }

    protected function pages(): array
    {
        return [IndexPage::class, FormPage::class, TelegramPostDetailPage::class];
    }

    protected function indexFields(): iterable
    {
        return [ID::make()->sortable(), Text::make('Channel', 'channel.name'), Text::make('Message ID', 'telegram_message_id'), Text::make('Text', 'text'), Number::make('Comments', 'comments_count')->sortable(), Switcher::make('Media', 'has_media'), Url::make('URL', 'url')->blank(), Date::make('Posted', 'posted_at')->format('Y-m-d H:i')];
    }

    protected function formFields(): iterable
    {
        return [Textarea::make('Text', 'text'), Url::make('URL', 'url')->required(), Date::make('Posted', 'posted_at')->withTime()];
    }

    protected function detailFields(): iterable
    {
        return [ID::make(), Text::make('Channel', 'channel.name'), Text::make('Message ID', 'telegram_message_id'), Textarea::make('Text', 'text'), Url::make('URL', 'url')->blank(), Date::make('Posted', 'posted_at')->format('Y-m-d H:i:s')];
    }
}
