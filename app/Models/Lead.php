<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'platform',
        'source_entity_type',
        'source_entity_id',
        'channel_or_group_id',
        'source_type',
        'post_id',
        'comment_id',
        'group_id',
        'keyword_id',
        'text',
        'url',
        'score',
        'status',
        'dedupe_key',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'source_entity_id' => 'integer',
            'channel_or_group_id' => 'integer',
        ];
    }

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
