<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkComment;

use App\Models\VkComment;
use App\Models\VkPost;
use App\MoonShine\Resources\Lead\LeadResource;
use App\MoonShine\Resources\VkComment\Pages\VkCommentIndexPage;
use App\MoonShine\Resources\VkPost\VkPostResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Leeto\MoonShineTree\Resources\TreeResource;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * Nested VK comments as MoonShine tree (plugin lee-to/moonshine-tree-resource).
 *
 * Hierarchy: parent_id (local FK). Sort column used only for display order —
 * drag&drop is disabled because structure comes from VK.
 *
 * @extends TreeResource
 */
class VkCommentResource extends TreeResource
{
    protected string $model = VkComment::class;

    protected string $title = 'VK Comments';

    /** Shown as tree title fallback */
    protected string $column = 'text';

    /** Order siblings by post time */
    protected string $sortColumn = 'posted_at';

    protected SortDirection $sortDirection = SortDirection::ASC;

    protected array $with = ['post', 'parent', 'children'];

    /**
     * Local parent FK for tree nesting.
     */
    public function treeKey(): ?string
    {
        return 'parent_id';
    }

    /**
     * Required by plugin; not used for drag-sort (sortable() = false).
     * Must not be the primary key.
     */
    public function sortKey(): string
    {
        return 'posted_at';
    }

    /**
     * VK hierarchy is read-only — no drag & drop reparenting.
     */
    public function sortable(): bool
    {
        return false;
    }

    public function wrappable(): bool
    {
        return true;
    }

    public function compactTree(): bool
    {
        return true;
    }

    public function treeItemTitle(Model $item): string
    {
        /** @var VkComment $item */
        $text = trim(preg_replace("/\s+/u", ' ', (string) $item->text) ?? '');

        if (mb_strlen($text) > 120) {
            $text = mb_substr($text, 0, 120).'…';
        }

        return $text !== '' ? $text : ('#'.$item->vk_comment_id);
    }

    public function treeItemBadgeText(Model $item): string
    {
        /** @var VkComment $item */
        if ((int) $item->depth === 0) {
            return 'root · '.$item->vk_comment_id;
        }

        return 'reply · '.$item->vk_comment_id;
    }

    public function treeItemBadgeColor(Model $item): Color
    {
        /** @var VkComment $item */
        return (int) $item->depth === 0 ? Color::GREEN : Color::BLUE;
    }

    public function treeItemDescription(Model $item): string
    {
        /** @var VkComment $item */
        $parts = [];

        if ($item->post) {
            $parts[] = e($item->post->display_name);
        }

        if ($item->posted_at) {
            $parts[] = $item->posted_at->format('Y-m-d H:i');
        }

        if ($item->author_id) {
            $parts[] = 'author: '.$item->author_id;
        }

        if ($item->url) {
            $parts[] = '<a href="'.e($item->url).'" target="_blank" rel="noopener">VK</a>';
        }

        return implode(' · ', $parts);
    }

    protected function indexFields(): iterable
    {
        // Tree UI replaces the table; fields kept for search / export / fallbacks
        return [
            ID::make()->sortable(),
            Number::make('VK id', 'vk_comment_id')->sortable(),
            Number::make('Depth', 'depth'),
            Textarea::make('Text', 'text'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i')->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            $this->postBelongsTo()->required(),
            Number::make('VK comment id', 'vk_comment_id')->required(),
            Number::make('Parent VK id', 'parent_vk_comment_id'),
            BelongsTo::make('Parent', 'parent', resource: self::class)->nullable(),
            BelongsTo::make('Thread root', 'threadRoot', resource: self::class)->nullable(),
            Number::make('Depth', 'depth')->default(0),
            Textarea::make('Text', 'text')->required(),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->withTime(),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            $this->postBelongsTo(),
            Number::make('VK comment id', 'vk_comment_id'),
            Number::make('Parent VK id', 'parent_vk_comment_id'),
            BelongsTo::make('Parent', 'parent', resource: self::class),
            BelongsTo::make('Thread root', 'threadRoot', resource: self::class),
            Number::make('Depth', 'depth'),
            Textarea::make('Text', 'text'),
            Number::make('Author id', 'author_id'),
            Url::make('Url', 'url'),
            Date::make('Posted at', 'posted_at')->format('Y-m-d H:i:s'),
            HasMany::make('Replies', 'children', resource: self::class),
            HasMany::make('Leads', 'leads', resource: LeadResource::class),
        ];
    }

    /**
     * BelongsTo Post with human-readable label (post text, not id).
     */
    private function postBelongsTo(): BelongsTo
    {
        return BelongsTo::make(
            'Post',
            'post',
            formatted: static fn (VkPost $model): string => $model->display_name,
            resource: VkPostResource::class,
        );
    }

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            VkCommentIndexPage::class,
            FormPage::class,
            DetailPage::class,
        ];
    }

    /**
     * Prefer roots with children loaded under them; siblings by posted_at.
     */
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder
            ->orderByRaw('COALESCE(thread_root_id, id) ASC')
            ->orderBy('depth', 'ASC')
            ->orderBy('posted_at', 'ASC')
            ->orderBy('id', 'ASC');
    }
}
