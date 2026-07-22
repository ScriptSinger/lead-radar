<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Lead;

use App\Models\Keyword;
use App\Models\Lead;
use App\Models\VkGroup;
use App\MoonShine\Resources\Keyword\KeywordResource;
use App\MoonShine\Resources\Lead\Pages\LeadIndexPage;
use App\MoonShine\Resources\VkComment\VkCommentResource;
use App\MoonShine\Resources\VkGroup\VkGroupResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * Operator queue for matched VK leads.
 *
 * @extends ModelResource<Lead>
 */
class LeadResource extends ModelResource
{
    protected string $model = Lead::class;

    protected string $title = 'Leads';

    protected string $column = 'id';

    protected array $with = ['group', 'keyword', 'post', 'comment'];

    protected string $sortColumn = 'created_at';

    protected SortDirection $sortDirection = SortDirection::DESC;

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            LeadIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Select::make('Status', 'status')->options($this->statusOptions())
                ->badge(static fn (string $status): Color => match ($status) {
                    'new' => Color::GREEN,
                    'processed' => Color::BLUE,
                    'ignored' => Color::GRAY,
                    default => Color::SECONDARY,
                })
                ->sortable(),
            Select::make('Source', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ])->sortable(),
            BelongsTo::make(
                'Keyword',
                'keyword',
                formatted: static fn (Keyword $k): string => $k->word,
                resource: KeywordResource::class,
            ),
            BelongsTo::make(
                'Group',
                'group',
                formatted: static fn (VkGroup $g): string => $g->name,
                resource: VkGroupResource::class,
            ),
            Text::make('Text', 'text')->changePreview(
                static function (mixed $value): string {
                    $text = trim(preg_replace("/\s+/u", ' ', (string) $value) ?? '');

                    return mb_strlen($text) > 100 ? mb_substr($text, 0, 100).'…' : $text;
                },
            ),
            Url::make('VK', 'url')->blank(),
            Number::make('Score', 'score')->sortable(),
            Date::make('Found', 'created_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Select::make('Source type', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ])->required(),
            BelongsTo::make(
                'Post',
                'post',
                formatted: static fn ($p) => $p->display_name ?? (string) $p->vk_post_id,
                resource: VkPostResource::class,
            )->required(),
            BelongsTo::make('Comment', 'comment', resource: VkCommentResource::class)->nullable(),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class)->required(),
            BelongsTo::make(
                'Keyword',
                'keyword',
                formatted: static fn (Keyword $k): string => $k->word,
                resource: KeywordResource::class,
            )->required(),
            Textarea::make('Text', 'text')->required(),
            Url::make('Url', 'url')->required()->blank(),
            Number::make('Score', 'score')->default(0),
            Select::make('Status', 'status')->options($this->statusOptions())->default('new'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Select::make('Status', 'status')->options($this->statusOptions()),
            Select::make('Source type', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ]),
            BelongsTo::make(
                'Keyword',
                'keyword',
                formatted: static fn (Keyword $k): string => $k->word,
                resource: KeywordResource::class,
            ),
            BelongsTo::make('Group', 'group', resource: VkGroupResource::class),
            BelongsTo::make(
                'Post',
                'post',
                formatted: static fn ($p) => $p->display_name ?? (string) $p->vk_post_id,
                resource: VkPostResource::class,
            ),
            BelongsTo::make('Comment', 'comment', resource: VkCommentResource::class),
            Textarea::make('Text', 'text'),
            Url::make('Url', 'url')->blank(),
            Number::make('Score', 'score'),
            Date::make('Created', 'created_at')->format('Y-m-d H:i:s'),
            Date::make('Updated', 'updated_at')->format('Y-m-d H:i:s'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            Select::make('Status', 'status')->options($this->statusOptions())->nullable(),
            Select::make('Source', 'source_type')->options([
                'post' => 'Post',
                'comment' => 'Comment',
            ])->nullable(),
            BelongsTo::make(
                'Group',
                'group',
                formatted: static fn (VkGroup $g): string => $g->name,
                resource: VkGroupResource::class,
            )->nullable(),
            BelongsTo::make(
                'Keyword',
                'keyword',
                formatted: static fn (Keyword $k): string => $k->word,
                resource: KeywordResource::class,
            )->nullable(),
        ];
    }

    /**
     * @return list<\MoonShine\Laravel\QueryTags\QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make('New', static fn (Builder $q) => $q->where('status', 'new')),
            QueryTag::make('Processed', static fn (Builder $q) => $q->where('status', 'processed')),
            QueryTag::make('Ignored', static fn (Builder $q) => $q->where('status', 'ignored')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'new' => 'New',
            'processed' => 'Processed',
            'ignored' => 'Ignored',
        ];
    }

    public function countByStatus(?string $status): int
    {
        $q = Lead::query();

        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->count();
    }

    #[AsyncMethod]
    public function markProcessed(): void
    {
        $this->updateStatus((int) request('resourceItem'), 'processed');
    }

    #[AsyncMethod]
    public function markIgnored(): void
    {
        $this->updateStatus((int) request('resourceItem'), 'ignored');
    }

    #[AsyncMethod]
    public function bulkMarkProcessed(): void
    {
        $this->bulkUpdateStatus('processed');
    }

    #[AsyncMethod]
    public function bulkMarkIgnored(): void
    {
        $this->bulkUpdateStatus('ignored');
    }

    private function updateStatus(int $id, string $status): void
    {
        if ($id <= 0) {
            return;
        }

        Lead::query()->whereKey($id)->update(['status' => $status]);
    }

    private function bulkUpdateStatus(string $status): void
    {
        $ids = request()->input('ids', []);

        if (! is_array($ids) || $ids === []) {
            return;
        }

        Lead::query()->whereIn('id', $ids)->update(['status' => $status]);
    }
}
