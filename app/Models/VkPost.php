<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VkPost extends Model
{
    protected $fillable = [
        'group_id',
        'vk_post_id',
        'text',
        'url',
        'author_id',
        'posted_at',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(VkGroup::class, 'group_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(VkComment::class, 'post_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'post_id');
    }
}
