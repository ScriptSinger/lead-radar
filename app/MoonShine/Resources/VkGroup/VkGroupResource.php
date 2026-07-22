<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkGroup;

use App\Models\VkGroup;
use App\MoonShine\Resources\VkGroup\Pages\VkGroupIndexPage;
use App\Services\Vk\GroupScanner;
use App\Services\Vk\ParserClient;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;
use Throwable;

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
            FormPage::class,
            DetailPage::class,
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

    #[AsyncMethod]
    public function scanNow(
        ParserClient $parser,
        GroupScanner $scanner,
    ): void {
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

        if (! $parser->health()) {
            toast('Parser is not healthy', ToastType::ERROR);

            return;
        }

        try {
            $stats = $scanner->scan($group, limit: 6, withComments: true);
            toast(sprintf(
                'Scan OK: posts +%d/~%d, comments +%d, leads +%d',
                $stats['posts_created'],
                $stats['posts_updated'],
                $stats['comments_created'] + $stats['comments_updated'],
                $stats['leads_created'],
            ));
        } catch (Throwable $e) {
            report($e);
            toast('Scan failed: '.$e->getMessage(), ToastType::ERROR);
        }
    }
}
