<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Lead\Pages;

use App\MoonShine\Resources\Lead\LeadResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;

/**
 * @extends IndexPage<LeadResource>
 */
class LeadIndexPage extends IndexPage
{
    /**
     * @return list<ComponentContract>
     */
    protected function metrics(): array
    {
        /** @var LeadResource $resource */
        $resource = $this->getResource();

        return [
            ValueMetric::make('New')->value(fn () => $resource->countByStatus('new')),
            ValueMetric::make('Processed')->value(fn () => $resource->countByStatus('processed')),
            ValueMetric::make('Ignored')->value(fn () => $resource->countByStatus('ignored')),
            ValueMetric::make('Total')->value(fn () => $resource->countByStatus(null)),
        ];
    }

    /**
     * Row + bulk status actions.
     *
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        $listName = $this->getListComponentName();

        return parent::buttons()
            ->prepend(
                ActionButton::make('Processed')
                    ->name('lead-processed')
                    ->icon('check')
                    ->success()
                    ->method(
                        method: 'markProcessed',
                        message: 'Marked as processed',
                        events: [$this->getListEventName()],
                    )
                    ->canSee(static function (mixed $item): bool {
                        $status = is_object($item) ? ($item->status ?? null) : data_get($item, 'status');

                        return $status === 'new' || $status === 'ignored';
                    }),
            )
            ->prepend(
                ActionButton::make('Ignored')
                    ->name('lead-ignored')
                    ->icon('x-mark')
                    ->warning()
                    ->method(
                        method: 'markIgnored',
                        message: 'Marked as ignored',
                        events: [$this->getListEventName()],
                    )
                    ->canSee(static function (mixed $item): bool {
                        $status = is_object($item) ? ($item->status ?? null) : data_get($item, 'status');

                        return $status === 'new' || $status === 'processed';
                    }),
            )
            ->add(
                ActionButton::make('Bulk → Processed')
                    ->name('lead-bulk-processed')
                    ->icon('check')
                    ->success()
                    ->bulk($listName)
                    ->method(
                        method: 'bulkMarkProcessed',
                        message: 'Selected leads marked processed',
                        events: [$this->getListEventName()],
                    ),
            )
            ->add(
                ActionButton::make('Bulk → Ignored')
                    ->name('lead-bulk-ignored')
                    ->icon('x-mark')
                    ->warning()
                    ->bulk($listName)
                    ->method(
                        method: 'bulkMarkIgnored',
                        message: 'Selected leads marked ignored',
                        events: [$this->getListEventName()],
                    ),
            );
    }
}
