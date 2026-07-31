<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Components\Metrics\Wrapped\Metric;
use App\MoonShine\Resources\VkPost\VkPostResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\Models\VkGroup;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Text;
use Throwable;


/**
 * @extends IndexPage<VkPostResource>
 */
class VkPostIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    // protected function fields(): iterable
    // {
    //     return [
    //         ID::make(),
    //     ];
    // }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            BelongsTo::make(
                'Group',
                'group',
                formatted: static fn (VkGroup $group): string => $group->name,
                resource: VkGroupResource::class,
            )->nullable(),
            Text::make('Text', 'text')->placeholder('Search post text'),
            Text::make('URL', 'url')->placeholder('Search by VK URL'),
            Date::make('Posted on', 'posted_at')->nullable(),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [];
    }

    /**
     * @return list<Metric>
     */
    protected function metrics(): array
    {
        return [];
    }

    /**
     * @param  TableBuilder  $component
     *
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer()
        ];
    }
}
