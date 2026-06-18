<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VkComment extends Model
{
    protected $fillable = [
        'post_id',
        'vk_comment_id',
        'parent_comment_id',
        'text',
        'author_id',
        'url',
        'posted_at',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(VkPost::class, 'post_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'comment_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(VkComment::class, 'parent_comment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(VkComment::class, 'parent_comment_id');
    }
}
