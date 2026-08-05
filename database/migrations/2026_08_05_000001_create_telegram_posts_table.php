<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('telegram_channels')->cascadeOnDelete();
            // Telegram message IDs are unique inside a channel, not globally.
            $table->unsignedBigInteger('telegram_message_id');
            $table->text('text')->nullable();
            $table->string('url');
            $table->unsignedBigInteger('author_telegram_id')->nullable();
            $table->boolean('has_media')->default(false);
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['channel_id', 'telegram_message_id']);
            $table->index(['channel_id', 'posted_at']);
            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_posts');
    }
};
