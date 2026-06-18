<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'source_type',
        'post_id',
        'comment_id',
        'group_id',
        'keyword_id',
        'text',
        'url',
        'score',
        'status',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(VkGroup::class, 'group_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(VkPost::class, 'post_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(VkComment::class, 'comment_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'keyword_id');
    }
}
