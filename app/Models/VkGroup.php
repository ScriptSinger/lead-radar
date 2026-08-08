<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VkGroup extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'url',
        'name',
        'active',
        'last_scan_at',
        'workspace_id',
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
