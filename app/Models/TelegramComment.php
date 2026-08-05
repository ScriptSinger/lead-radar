<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TelegramComment extends Model {
 protected $fillable=['post_id','telegram_message_id','parent_telegram_message_id','parent_id','thread_root_id','depth','text','author_telegram_id','posted_at'];
 protected function casts(): array{return['posted_at'=>'datetime'];}
 public function post(): BelongsTo{return $this->belongsTo(TelegramPost::class,'post_id');}
 public function parent(): BelongsTo{return $this->belongsTo(self::class,'parent_id');}
 public function children(): HasMany{return $this->hasMany(self::class,'parent_id');}
}
