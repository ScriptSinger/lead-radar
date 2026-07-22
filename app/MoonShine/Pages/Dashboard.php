<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Keyword;
use App\Models\Lead;
use App\Models\ScanRun;
use App\Models\VkComment;
use App\Models\VkGroup;
use App\Models\VkPost;
use Carbon\Carbon;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Layout\LineBreak;
use MoonShine\UI\Components\Metrics\Wrapped\ValueMetric;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

#[SkipMenu]
class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Lead Radar';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $newLeads = Lead::query()->where('status', 'new')->count();
        $processed = Lead::query()->where('status', 'processed')->count();
        $ignored = Lead::query()->where('status', 'ignored')->count();
        $activeGroups = VkGroup::query()->where('active', true)->count();
        $lastScan = VkGroup::query()->whereNotNull('last_scan_at')->max('last_scan_at');
        $posts = VkPost::query()->count();
        $comments = VkComment::query()->count();
        $keywords = Keyword::query()->count();

        $lastScanLabel = $lastScan
            ? Carbon::parse($lastScan)->format('Y-m-d H:i')
            : 'never';

        $failedRuns24h = ScanRun::query()
            ->whereIn('status', [ScanRun::STATUS_FAILED, ScanRun::STATUS_PARSER_DOWN])
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $recentRows = Lead::query()
            ->with(['keyword', 'group'])
            ->where('status', 'new')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (Lead $lead): array => [
                'keyword' => $lead->keyword?->word ?? '—',
                'group' => $lead->group?->name ?? '—',
                'text' => mb_strlen($lead->text) > 80
                    ? mb_substr($lead->text, 0, 80).'…'
                    : $lead->text,
                'url' => $lead->url,
                'found' => $lead->created_at?->format('Y-m-d H:i') ?? '',
            ])
            ->all();

        $scanRows = ScanRun::query()
            ->with('group')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(static fn (ScanRun $run): array => [
                'id' => $run->id,
                'group' => $run->group?->name ?? '—',
                'status' => $run->status,
                'posts' => $run->posts_fetched,
                'leads' => $run->leads_created,
                'ms' => $run->duration_ms ?? '—',
                'started' => $run->started_at?->format('Y-m-d H:i:s') ?? '',
                'error' => $run->error_message
                    ? mb_substr($run->error_message, 0, 80)
                    : '',
            ])
            ->all();

        return [
            Grid::make([
                Column::make([
                    ValueMetric::make('New leads')->value($newLeads),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Processed')->value($processed),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Ignored')->value($ignored),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Active groups')->value($activeGroups),
                ])->columnSpan(3),
            ]),

            LineBreak::make(),

            Grid::make([
                Column::make([
                    ValueMetric::make('Last scan')->value($lastScanLabel),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Failed scans 24h')->value($failedRuns24h),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Posts in DB')->value($posts),
                ])->columnSpan(3),
                Column::make([
                    ValueMetric::make('Keywords')->value($keywords),
                ])->columnSpan(3),
            ]),

            LineBreak::make(),

            Box::make('New leads (latest 10)', [
                TableBuilder::make([
                    Text::make('Keyword', 'keyword'),
                    Text::make('Group', 'group'),
                    Text::make('Text', 'text'),
                    Url::make('VK', 'url')->blank(),
                    Text::make('Found', 'found'),
                ], $recentRows),
            ]),

            LineBreak::make(),

            Box::make('Recent scan runs', [
                TableBuilder::make([
                    Text::make('ID', 'id'),
                    Text::make('Group', 'group'),
                    Text::make('Status', 'status'),
                    Text::make('Posts', 'posts'),
                    Text::make('Leads+', 'leads'),
                    Text::make('ms', 'ms'),
                    Text::make('Started', 'started'),
                    Text::make('Error', 'error'),
                ], $scanRows),
            ]),
        ];
    }
}
