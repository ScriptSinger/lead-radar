<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Telegram;

use App\Enums\ScanStatus;
use App\Models\TelegramChannel;
use App\Models\TelegramScanRun;
use App\MoonShine\Concerns\ScopesToActiveWorkspace;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/** @extends ModelResource<TelegramScanRun> */
class TelegramScanRunResource extends ModelResource
{
    use ScopesToActiveWorkspace;

    protected string $model = TelegramScanRun::class;

    protected string $title = 'Telegram Scan Runs';

    protected string $column = 'id';

    protected array $with = ['channel'];

    protected string $sortColumn = 'id';

    protected SortDirection $sortDirection = SortDirection::DESC;

    /**
     * @return ListOf<Action>
     */
    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [
            Action::VIEW,
        ]);
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToActiveWorkspace($builder);
    }

    protected function modifyItemQueryBuilder(Builder $builder): Builder
    {
        return $this->scopeToActiveWorkspace($builder);
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Channel',
                'channel',
                formatted: static fn (TelegramChannel $channel): string => $channel->name,
                resource: TelegramChannelResource::class,
            ),
            Text::make('Status', 'status')->badge(
                static fn (string $status): Color => ScanStatus::colorFor($status)
            ),
            Text::make('Trigger', 'trigger'),
            Number::make('Posts', 'posts_fetched'),
            Number::make('Leads +', 'leads_created'),
            Number::make('Errors', 'error_count'),
            Date::make('Started', 'started_at')->format('Y-m-d H:i:s')->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Channel', 'channel', resource: TelegramChannelResource::class),
            Text::make('Status', 'status')->badge(
                static fn (string $status): Color => ScanStatus::colorFor($status)
            ),
            Text::make('Trigger', 'trigger'),
            Number::make('Posts fetched', 'posts_fetched'),
            Number::make('Posts created', 'posts_created'),
            Number::make('Leads created', 'leads_created'),
            Text::make('Error', 'error_message'),
            Date::make('Started', 'started_at')->format('Y-m-d H:i:s'),
            Date::make('Finished', 'finished_at')->format('Y-m-d H:i:s'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Channel',
                'channel',
                formatted: static fn (TelegramChannel $channel): string => $channel->name,
                resource: TelegramChannelResource::class,
            )->nullable(),
            Select::make('Status', 'status')->options(ScanStatus::options())->nullable(),
            Select::make('Trigger', 'trigger')->options([
                'schedule' => 'Schedule',
                'admin' => 'Admin',
                'manual' => 'Manual',
                'job' => 'Job',
            ])->nullable(),
            Date::make('Started on', 'started_at')->nullable(),
        ];
    }
}
