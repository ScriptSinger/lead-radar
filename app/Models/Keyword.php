<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'word',
        'type',
        'match_mode',
        'negative_words',
        'score',
        'workspace_id',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'keyword_id');
    }
}
