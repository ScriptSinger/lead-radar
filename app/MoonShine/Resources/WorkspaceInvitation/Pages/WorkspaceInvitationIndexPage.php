<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\WorkspaceInvitation\Pages;

use App\Models\WorkspaceInvitation;
use App\MoonShine\Resources\WorkspaceInvitation\WorkspaceInvitationResource;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

/**
 * @extends IndexPage<WorkspaceInvitationResource>
 */
final class WorkspaceInvitationIndexPage extends IndexPage
{
    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->prepend(
            ActionButton::make('Resend')
                ->name('workspace-invitation-resend')
                ->icon('arrow-path')
                ->primary()
                ->method(
                    method: 'resend',
                    message: 'A new invitation link was sent',
                    events: [$this->getListEventName()],
                )
                ->canSee(static function (mixed $item): bool {
                    $invitation = $item instanceof WorkspaceInvitation ? $item : null;

                    return $invitation !== null && $invitation->accepted_at === null;
                }),
        );
    }
}
