<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ScanSetting;

use App\Models\ScanSetting;
use App\Models\VkGroup;
use App\MoonShine\Resources\ScanSetting\Pages\ScanSettingIndexPage;
use App\Services\Vk\ScanSchedule;
use App\Support\PostWindow;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Textarea;

/**
 * Singleton scan policy: interval, rate limits, post window.
 *
 * @extends ModelResource<ScanSetting>
 */
class ScanSettingResource extends ModelResource
{
    protected string $model = ScanSetting::class;

    protected string $title = 'Scan Settings';

    protected string $column = 'name';

    protected string $sortColumn = 'id';

    protected SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            ScanSettingIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    /**
     * @return ListOf<Action>
     */
    protected function activeActions(): ListOf
    {
        // One row is seeded; operators edit, do not mass-create policies in v1
        return new ListOf(Action::class, [
            Action::VIEW,
            Action::UPDATE,
        ]);
    }

    /**
     * Immediately queue a scan wave (does not wait for interval).
     */
    #[AsyncMethod]
    public function runNow(): void
    {
        $result = app(ScanSchedule::class)->dispatchNow('admin');

        if (! $result['dispatched']) {
            toast('No active VK groups to scan', ToastType::WARNING);

            return;
        }

        toast(sprintf(
            'Scan wave queued for %d group(s) (limit=%d, comments=%s). Worker must be running on queue vk.scan.',
            $result['active_groups'],
            $result['limit'],
            $result['with_comments'] ? 'yes' : 'no',
        ));
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Switcher::make('Schedule on', 'schedule_enabled'),
            Number::make('Every (min)', 'interval_minutes')->sortable(),
            Number::make('Delay groups (s)', 'group_delay_seconds'),
            Number::make('Posts limit', 'scan_limit'),
            Switcher::make('Comments', 'with_comments'),
            Select::make('Window', 'post_window')->options($this->windowOptions()),
            Date::make('Last dispatch', 'last_dispatched_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        $active = VkGroup::query()->where('active', true)->count();
        $settings = ScanSetting::query()->orderBy('id')->first();
        $waveHint = $settings
            ? sprintf(
                '~%d min estimated wave for %d active groups (delay %ds). Keep interval ≥ wave length.',
                max(1, (int) ceil($settings->estimatedWaveSeconds($active) / 60)),
                $active,
                $settings->normalizedGroupDelaySeconds(),
            )
            : 'Run seeder / open after first save to see wave estimate.';

        return [
            Box::make('Schedule', [
                Alert::make('information-circle', 'primary')
                    ->content(
                        'Save only stores settings — it does NOT start a scan. '
                        .'Automatic waves run when Interval has passed since Last dispatched. '
                        .'To start immediately: list page → «Run scan now», or clear Last dispatched / set it in the past. '
                        .$waveHint
                    ),
                Switcher::make('Schedule enabled', 'schedule_enabled')
                    ->hint('Off = scheduler tick does nothing (manual «Run scan now» still works).'),
                Number::make('Interval (minutes)', 'interval_minutes')
                    ->min(5)
                    ->max(1440)
                    ->required()
                    ->hint('How often a new fan-out starts. Competitive leads: 20–40. Many groups: raise so wave fits.'),
                Date::make('Last dispatched at', 'last_dispatched_at')
                    ->withTime()
                    ->format('Y-m-d H:i:s')
                    ->hint('Set by scheduler / Run scan now. Clear or set in the past to allow the next auto tick sooner.'),
            ]),

            Box::make('Rate limit & volume', [
                Number::make('Group delay (seconds)', 'group_delay_seconds')
                    ->min(0)
                    ->max(600)
                    ->required()
                    ->hint('Stagger between groups in one wave (anti-ban).'),
                Number::make('Posts per group (limit)', 'scan_limit')
                    ->min(1)
                    ->max(30)
                    ->required()
                    ->hint('Top-N posts from VK wall each scan.'),
                Switcher::make('Scrape comments', 'with_comments')
                    ->hint('Much heavier. Disable if captcha / 50+ groups.'),
            ]),

            Box::make('Matching window', [
                Select::make('Post window', 'post_window')
                    ->options($this->windowOptions())
                    ->required()
                    ->hint('Which scraped posts get comments + keyword match.'),
                Textarea::make('Notes', 'notes')->nullable(),
            ]),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Switcher::make('Schedule enabled', 'schedule_enabled'),
            Number::make('Interval minutes', 'interval_minutes'),
            Number::make('Group delay seconds', 'group_delay_seconds'),
            Number::make('Scan limit', 'scan_limit'),
            Switcher::make('With comments', 'with_comments'),
            Select::make('Post window', 'post_window')->options($this->windowOptions()),
            Date::make('Last dispatched', 'last_dispatched_at')->format('Y-m-d H:i:s'),
            Textarea::make('Notes', 'notes'),
            Date::make('Updated', 'updated_at')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function windowOptions(): array
    {
        return [
            PostWindow::MODE_SINCE_LAST_SCAN => 'Since last scan (recommended)',
            PostWindow::MODE_TODAY => 'Today only',
            PostWindow::MODE_ALL => 'All posts in limit (no date filter)',
        ];
    }

    protected function rules($item): array
    {
        return [
            'schedule_enabled' => ['sometimes', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'group_delay_seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'scan_limit' => ['required', 'integer', 'min:1', 'max:30'],
            'with_comments' => ['sometimes', 'boolean'],
            'post_window' => ['required', 'string', 'in:since_last_scan,today,all'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
