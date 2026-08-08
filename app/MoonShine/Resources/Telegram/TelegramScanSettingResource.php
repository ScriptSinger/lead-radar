<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram;

use App\Models\TelegramScanSetting;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Textarea;

/** @extends ModelResource<TelegramScanSetting> */
class TelegramScanSettingResource extends ModelResource
{
    protected string $model = TelegramScanSetting::class;

    protected string $title = 'Telegram Scan Settings';

    protected string $column = 'name';

    protected string $sortColumn = 'id';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Switcher::make('Schedule', 'schedule_enabled')->sortable(),
            Number::make('Every min', 'interval_minutes')->sortable(),
            Number::make('Delay s', 'channel_delay_seconds')->sortable(),
            Number::make('Posts', 'scan_limit')->sortable(),
            Switcher::make('Comments', 'with_comments')->sortable(),
            Number::make('Comments limit', 'comments_limit')->sortable(),
            Date::make('Last dispatch', 'last_dispatched_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Switcher::make('Schedule enabled', 'schedule_enabled'),
            Number::make('Interval minutes', 'interval_minutes')->min(1)->max(1440),
            Number::make('Channel delay seconds', 'channel_delay_seconds')->min(0)->max(600),
            Number::make('Posts per source', 'scan_limit')->min(1)->max(100),
            Switcher::make('Scan comments', 'with_comments'),
            Number::make('Comments per post', 'comments_limit')->min(1)->max(100),
            Date::make('Last dispatched', 'last_dispatched_at')->withTime(),
            Textarea::make('Notes', 'notes'),
        ];
    }
}
