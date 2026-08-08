<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\ScanStatus;
use App\Models\Keyword;
use App\Models\Lead;
use App\Models\ScanRun;
use App\Models\TelegramChannel;
use App\Models\TelegramComment;
use App\Models\TelegramPost;
use App\Models\TelegramScanRun;
use App\Models\VkComment;
use App\Models\VkGroup;
use App\Models\VkPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        $telegramChannelNames = TelegramChannel::query()->pluck('name', 'id');
        $recentLeads = $this->recentLeadRows($telegramChannelNames);
        $recentScans = $this->recentScanRows();

        return [
            Grid::make([
                Column::make([ValueMetric::make('New leads')->value(Lead::query()->where('status', 'new')->count())])->columnSpan(3),
                Column::make([ValueMetric::make('Processed')->value(Lead::query()->where('status', 'processed')->count())])->columnSpan(3),
                Column::make([ValueMetric::make('Ignored')->value(Lead::query()->where('status', 'ignored')->count())])->columnSpan(3),
                Column::make([ValueMetric::make('Keywords')->value(Keyword::query()->count())])->columnSpan(3),
            ]),

            LineBreak::make(),

            Box::make('VK', [
                Grid::make([
                    Column::make([ValueMetric::make('Active groups')->value(VkGroup::query()->where('active', true)->count())])->columnSpan(3),
                    Column::make([ValueMetric::make('Last scan')->value($this->lastScanLabel(VkGroup::query()->max('last_scan_at')))])->columnSpan(3),
                    Column::make([ValueMetric::make('Posts / comments')->value(VkPost::query()->count().' / '.VkComment::query()->count())])->columnSpan(3),
                    Column::make([ValueMetric::make('Failed scans, 24h')->value($this->failedRuns24h(ScanRun::class))])->columnSpan(3),
                ]),
            ]),

            Box::make('Telegram', [
                Grid::make([
                    Column::make([ValueMetric::make('Active channels')->value(TelegramChannel::query()->where('active', true)->count())])->columnSpan(3),
                    Column::make([ValueMetric::make('Last scan')->value($this->lastScanLabel(TelegramChannel::query()->max('last_scan_at')))])->columnSpan(3),
                    Column::make([ValueMetric::make('Posts / comments')->value(TelegramPost::query()->count().' / '.TelegramComment::query()->count())])->columnSpan(3),
                    Column::make([ValueMetric::make('Failed scans, 24h')->value($this->failedRuns24h(TelegramScanRun::class))])->columnSpan(3),
                ]),
            ]),

            LineBreak::make(),

            Box::make('New leads (latest 10)', [
                TableBuilder::make([
                    Text::make('Platform', 'platform'),
                    Text::make('Source', 'source'),
                    Text::make('Keyword', 'keyword'),
                    Text::make('Text', 'text'),
                    Url::make('Open source', 'url')->blank(),
                    Text::make('Found', 'found'),
                ], $recentLeads),
            ]),

            LineBreak::make(),

            Box::make('Recent scan runs', [
                TableBuilder::make([
                    Text::make('Platform', 'platform'),
                    Text::make('Source', 'source'),
                    Text::make('Status', 'status'),
                    Text::make('Posts', 'posts'),
                    Text::make('Leads+', 'leads'),
                    Text::make('ms', 'ms'),
                    Text::make('Started', 'started'),
                    Text::make('Error', 'error'),
                ], $recentScans),
            ]),
        ];
    }

    /** @param Collection<int, string> $telegramChannelNames */
    private function recentLeadRows(Collection $telegramChannelNames): array
    {
        return Lead::query()
            ->with(['keyword', 'group'])
            ->where('status', 'new')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static function (Lead $lead) use ($telegramChannelNames): array {
                $isTelegram = $lead->platform === 'telegram';

                return [
                    'platform' => $isTelegram ? 'Telegram' : 'VK',
                    'source' => $isTelegram
                        ? ($telegramChannelNames->get($lead->channel_or_group_id) ?? '—')
                        : ($lead->group?->name ?? '—'),
                    'keyword' => $lead->keyword?->word ?? '—',
                    'text' => self::truncate($lead->text, 80),
                    'url' => $lead->url,
                    'found' => $lead->created_at?->format('Y-m-d H:i') ?? '',
                ];
            })
            ->all();
    }

    private function recentScanRows(): array
    {
        $vkRows = ScanRun::query()->with('group')->latest('id')->limit(10)->get()->map(
            static fn (ScanRun $run): array => self::scanRow(
                platform: 'VK',
                source: $run->group?->name ?? '—',
                status: $run->status,
                posts: $run->posts_fetched,
                leads: $run->leads_created,
                duration: $run->duration_ms,
                startedAt: $run->started_at,
                error: $run->error_message,
            )
        );
        $telegramRows = TelegramScanRun::query()->with('channel')->latest('id')->limit(10)->get()->map(
            static fn (TelegramScanRun $run): array => self::scanRow(
                platform: 'Telegram',
                source: $run->channel?->name ?? '—',
                status: $run->status,
                posts: $run->posts_fetched,
                leads: $run->leads_created,
                duration: $run->duration_ms,
                startedAt: $run->started_at,
                error: $run->error_message,
            )
        );

        return collect($vkRows->all())->merge($telegramRows->all())
            ->sortByDesc('started_at_sort')
            ->take(10)
            ->map(static function (array $row): array {
                unset($row['started_at_sort']);

                return $row;
            })
            ->values()
            ->all();
    }

    private static function scanRow(string $platform, string $source, string $status, int $posts, int $leads, ?int $duration, mixed $startedAt, ?string $error): array
    {
        return [
            'platform' => $platform,
            'source' => $source,
            'status' => ScanStatus::tryFrom($status)?->label() ?? $status,
            'posts' => $posts,
            'leads' => $leads,
            'ms' => $duration ?? '—',
            'started' => $startedAt?->format('Y-m-d H:i:s') ?? '',
            'started_at_sort' => $startedAt?->getTimestamp() ?? 0,
            'error' => self::truncate($error, 80),
        ];
    }

    private function failedRuns24h(string $model): int
    {
        return $model::query()
            ->where('status', ScanStatus::FAILED->value)
            ->where('started_at', '>=', now()->subDay())
            ->count();
    }

    private function lastScanLabel(?string $lastScan): string
    {
        return $lastScan ? Carbon::parse($lastScan)->format('Y-m-d H:i') : 'never';
    }

    private static function truncate(?string $text, int $limit): string
    {
        $text = trim((string) $text);

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }
}
