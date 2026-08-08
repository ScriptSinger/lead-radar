<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Workspace\Pages;

use App\Models\Workspace;
use App\MoonShine\Resources\Workspace\WorkspaceResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

/**
 * @extends IndexPage<WorkspaceResource>
 */
final class WorkspaceIndexPage extends IndexPage
{
    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->prepend(
            ActionButton::make('Use workspace')
                ->name('workspace-activate')
                ->icon('check')
                ->primary()
                ->method(
                    method: 'activate',
                    message: 'Workspace selected',
                    events: [$this->getListEventName()],
                )
                ->canSee(static function (mixed $item): bool {
                    return $item instanceof Workspace && session('workspace_id') !== $item->id;
                }),
        );
    }
}
