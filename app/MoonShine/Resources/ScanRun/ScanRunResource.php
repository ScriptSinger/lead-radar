<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ScanRun;

use App\Models\ScanRun;
use App\Models\VkGroup;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * Read-only observability for scan runs.
 *
 * @extends ModelResource<ScanRun>
 */
class ScanRunResource extends ModelResource
{
    protected string $model = ScanRun::class;

    protected string $title = 'Scan Runs';

    protected string $column = 'id';

    protected array $with = ['group'];

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

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Group',
                'group',
                formatted: static fn (VkGroup $g): string => $g->name,
                resource: VkGroupResource::class,
            ),
            Text::make('Trigger', 'trigger'),
            Text::make('Status', 'status')->badge(
                static fn (string $status): Color => match ($status) {
                    'success' => Color::GREEN,
                    'running' => Color::BLUE,
                    'parser_down' => Color::ORANGE,
                    'failed' => Color::RED,
                    default => Color::GRAY,
                }
            ),
            Number::make('Posts', 'posts_fetched'),
            Number::make('Leads +', 'leads_created'),
            Number::make('Errors', 'error_count'),
            Number::make('ms', 'duration_ms'),
            Date::make('Started', 'started_at')->format('Y-m-d H:i:s')->sortable(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class),
            Text::make('Trigger', 'trigger'),
            Text::make('Status', 'status'),
            Number::make('Limit', 'limit'),
            Text::make('With comments', 'with_comments')->changePreview(
                static fn ($v) => $v ? 'yes' : 'no'
            ),
            Number::make('Posts fetched', 'posts_fetched'),
            Number::make('Posts created', 'posts_created'),
            Number::make('Posts updated', 'posts_updated'),
            Number::make('Comments fetched', 'comments_fetched'),
            Number::make('Comments created', 'comments_created'),
            Number::make('Comments updated', 'comments_updated'),
            Number::make('Leads created', 'leads_created'),
            Number::make('Leads updated', 'leads_updated'),
            Number::make('Error count', 'error_count'),
            Textarea::make('Error message', 'error_message'),
            Textarea::make('Errors', 'errors')->changePreview(
                static function ($value) {
                    if (is_array($value)) {
                        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
                    }

                    return (string) $value;
                }
            ),
            Number::make('Duration ms', 'duration_ms'),
            Date::make('Started', 'started_at')->format('Y-m-d H:i:s'),
            Date::make('Finished', 'finished_at')->format('Y-m-d H:i:s'),
        ];
    }
}
