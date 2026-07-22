<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkGroup;

use App\Jobs\ScanVkGroupJob;
use App\Models\VkGroup;
use App\MoonShine\Resources\VkGroup\Pages\VkGroupDetailPage;
use App\MoonShine\Resources\VkGroup\Pages\VkGroupFormPage;
use App\MoonShine\Resources\VkGroup\Pages\VkGroupIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

/**
 * @extends ModelResource<VkGroup>
 */
class VkGroupResource extends ModelResource
{
    protected string $model = VkGroup::class;

    protected string $title = 'VK Groups';

    /** Shown in BelongsTo labels on related resources */
    protected string $column = 'name';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            VkGroupIndexPage::class,
            VkGroupFormPage::class,
            VkGroupDetailPage::class,
        ];
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Url::make('Url', 'url')->blank(),
            Switcher::make('Active', 'active'),
            Date::make('Last scan', 'last_scan_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Text::make('Name', 'name')->required(),
            Url::make('Url', 'url')->required(),
            Switcher::make('Active', 'active'),
            Date::make('Last scan', 'last_scan_at')
                ->withTime()
                ->format('Y-m-d H:i:s'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name'),
            Url::make('Url', 'url')->blank(),
            Switcher::make('Active', 'active'),
            Date::make('Last scan', 'last_scan_at')->format('Y-m-d H:i:s'),
            Date::make('Created at', 'created_at')->format('Y-m-d H:i:s'),
            Date::make('Updated at', 'updated_at')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Queue a scan for this group (does not block the admin request).
     */
    #[AsyncMethod]
    public function scanNow(): void
    {
        $id = (int) request('resourceItem');

        if ($id <= 0) {
            toast('Group not found', ToastType::ERROR);

            return;
        }

        $group = VkGroup::query()->find($id);

        if ($group === null) {
            toast('Group not found', ToastType::ERROR);

            return;
        }

        if (! $group->active) {
            toast('Group is inactive', ToastType::WARNING);

            return;
        }

        $settings = \App\Models\ScanSetting::current();
        $limit = $settings->normalizedLimit();
        $withComments = (bool) $settings->with_comments;

        if (! \App\Support\VkUrl::isValid($group->url)) {
            toast(\App\Support\VkUrl::validationMessage(), ToastType::ERROR);

            return;
        }

        ScanVkGroupJob::dispatch($group->id, $limit, $withComments, 'admin');

        toast(sprintf(
            'Scan queued for «%s» (queue: vk.scan)',
            $group->name,
        ));
    }
}
