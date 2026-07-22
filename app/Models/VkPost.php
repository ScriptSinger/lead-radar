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

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'author_id' => 'integer',
        ];
    }

    /**
     * Human-readable label for admin selects / filters.
     */
    public function getDisplayNameAttribute(): string
    {
        $text = trim(preg_replace("/\s+/u", ' ', (string) ($this->text ?? '')) ?? '');

        if ($text !== '') {
            return mb_strlen($text) > 80 ? mb_substr($text, 0, 80).'…' : $text;
        }

        return (string) $this->vk_post_id;
    }

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
