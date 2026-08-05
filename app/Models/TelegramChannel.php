<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChannel extends Model
{
    protected $fillable = [
        'url',
        'username',
        'telegram_channel_id',
        'access_hash',
        'name',
        'active',
        'last_message_id',
        'last_scan_at',
    ];

    protected $hidden = [
        'access_hash',
    ];

    protected function casts(): array
    {
        return [
            'telegram_channel_id' => 'integer',
            'last_message_id' => 'integer',
            'active' => 'boolean',
            'last_scan_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TelegramPost::class, 'channel_id');
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(TelegramScanRun::class, 'channel_id');
    }
}
