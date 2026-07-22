<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VkGroup extends Model
{
    protected $fillable = [
        'url',
        'name',
        'active',
        'last_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_scan_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(VkPost::class, 'group_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'group_id');
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(ScanRun::class, 'group_id');
    }
}
