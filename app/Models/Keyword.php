<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = [
        'word',
        'type',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'keyword_id');
    }
}
