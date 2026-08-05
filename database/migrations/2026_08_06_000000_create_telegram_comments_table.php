<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('telegram_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_message_id');
            $table->unsignedBigInteger('parent_telegram_message_id')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('telegram_comments')->nullOnDelete();
            $table->foreignId('thread_root_id')->nullable()->constrained('telegram_comments')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->text('text')->nullable();
            $table->unsignedBigInteger('author_telegram_id')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['post_id', 'telegram_message_id']);
            $table->index(['post_id', 'thread_root_id', 'depth']);
        });
    }
    public function down(): void { Schema::dropIfExists('telegram_comments'); }
};
