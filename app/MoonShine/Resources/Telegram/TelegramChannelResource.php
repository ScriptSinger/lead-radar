<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram;

use App\Jobs\ScanTelegramChannelJob;
use App\Models\TelegramChannel;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

/** @extends ModelResource<TelegramChannel> */
class TelegramChannelResource extends ModelResource
{
    protected string $model = TelegramChannel::class;

    protected string $title = 'Telegram Channels';

    protected string $column = 'name';

    protected string $sortColumn = 'name';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function indexFields(): iterable
    {
        return [ID::make()->sortable(), Text::make('Name', 'name')->sortable(), Text::make('Username', 'username')->sortable(), Url::make('URL', 'url')->blank(), Switcher::make('Active', 'active')->sortable(), Date::make('Last scan', 'last_scan_at')->format('Y-m-d H:i')->sortable()];
    }

    protected function formFields(): iterable
    {
        return [Text::make('Name', 'name')->required(), Url::make('Public URL', 'url')->required(), Switcher::make('Active', 'active')];
    }

    protected function detailFields(): iterable
    {
        return [ID::make(), Text::make('Name', 'name'), Text::make('Username', 'username'), Url::make('URL', 'url')->blank(), Switcher::make('Active', 'active'), Text::make('Telegram ID', 'telegram_channel_id'), Date::make('Last scan', 'last_scan_at')->format('Y-m-d H:i:s')];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Name', 'name')->placeholder('Search by channel name'),
            Text::make('Username', 'username')->placeholder('Search by @username'),
            Text::make('URL', 'url')->placeholder('Search by Telegram URL'),
            Switcher::make('Active', 'active')->nullable(),
            Date::make('Last scan', 'last_scan_at')->nullable(),
        ];
    }

    #[AsyncMethod]
    public function scanNow(): void
    {
        $c = TelegramChannel::query()->find((int) request('resourceItem'));
        if ($c?->active) {
            ScanTelegramChannelJob::dispatch($c->id, (int) config('services.telegram.scan.limit', 20), 'admin');
            toast('Telegram scan queued');
        }
    }
}
