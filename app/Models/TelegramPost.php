<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPost extends Model
{
    protected $fillable = [
        'channel_id',
        'telegram_message_id',
        'text',
        'url',
        'author_telegram_id',
        'has_media',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_message_id' => 'integer',
            'author_telegram_id' => 'integer',
            'has_media' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $this->text) ?? '');

        if ($text !== '') {
            return mb_strlen($text) > 80 ? mb_substr($text, 0, 80).'…' : $text;
        }

        return (string) $this->telegram_message_id;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TelegramChannel::class, 'channel_id');
    }
}
