<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\TelegramScanRun;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/** @extends ModelResource<TelegramScanRun> */
class TelegramScanRunResource extends ModelResource
{
    protected string $model = TelegramScanRun::class;

    protected string $title = 'Telegram Scan Runs';

    protected string $column = 'id';

    protected array $with = ['channel'];

    protected function indexFields(): iterable
    {
        return [ID::make()->sortable(), Text::make('Channel', 'channel.name'), Text::make('Status', 'status'), Text::make('Trigger', 'trigger'), Number::make('Posts', 'posts_fetched'), Number::make('Leads +', 'leads_created'), Number::make('Errors', 'error_count'), Date::make('Started', 'started_at')->format('Y-m-d H:i:s')];
    }

    protected function detailFields(): iterable
    {
        return [ID::make(), Text::make('Channel', 'channel.name'), Text::make('Status', 'status'), Text::make('Trigger', 'trigger'), Number::make('Posts fetched', 'posts_fetched'), Number::make('Posts created', 'posts_created'), Number::make('Leads created', 'leads_created'), Text::make('Error', 'error_message'), Date::make('Started', 'started_at')->format('Y-m-d H:i:s'), Date::make('Finished', 'finished_at')->format('Y-m-d H:i:s')];
    }
}
