<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VkComment extends Model
{
    protected $fillable = [
        'post_id',
        'vk_comment_id',
        'parent_vk_comment_id',
        'parent_id',
        'thread_root_id',
        'depth',
        'text',
        'author_id',
        'url',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'vk_comment_id' => 'integer',
            'parent_vk_comment_id' => 'integer',
            'parent_id' => 'integer',
            'thread_root_id' => 'integer',
            'depth' => 'integer',
            'author_id' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(VkPost::class, 'post_id');
    }

    /**
     * Immediate parent comment (local FK).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct replies to this comment.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Root comment of the thread (self for roots).
     */
    public function threadRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'thread_root_id');
    }

    /**
     * All comments in the same thread (including root), same post.
     */
    public function threadSiblings(): HasMany
    {
        return $this->hasMany(self::class, 'thread_root_id', 'thread_root_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'comment_id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('parent_id')->orWhere('depth', 0);
        });
    }

    public function scopeNested(Builder $query): Builder
    {
        return $query->where('depth', '>', 0);
    }

    public function scopeInThread(Builder $query, int $threadRootId): Builder
    {
        return $query->where('thread_root_id', $threadRootId);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null || (int) $this->depth === 0;
    }

    /**
     * Context snippets for lead matching / notifications.
     *
     * @return array{self: string, parent: ?string, thread_root: ?string}
     */
    public function contextTexts(): array
    {
        $this->loadMissing(['parent', 'threadRoot']);

        // Only include thread root when it differs from self and immediate parent
        // (for depth=1 parent === thread root, so avoid duplicate snippets).
        $threadRootText = null;
        if (
            $this->threadRoot
            && $this->thread_root_id !== $this->id
            && $this->thread_root_id !== $this->parent_id
        ) {
            $threadRootText = $this->threadRoot->text;
        }

        return [
            'self' => $this->text,
            'parent' => $this->parent?->text,
            'thread_root' => $threadRootText,
        ];
    }
}
