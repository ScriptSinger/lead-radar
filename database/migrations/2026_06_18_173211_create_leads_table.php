<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16)->default('vk'); // vk|telegram
            $table->string('source_entity_type', 32)->nullable(); // post|comment
            $table->unsignedBigInteger('source_entity_id')->nullable();
            $table->unsignedBigInteger('channel_or_group_id')->nullable();
            // VK compatibility fields. They are null for Telegram leads.
            $table->string('source_type'); // 'post' or 'comment'
            $table->foreignId('post_id')->nullable()->constrained('vk_posts')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('vk_comments')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('vk_groups')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->text('text');
            $table->string('url');
            $table->unsignedInteger('score')->default(0);
            $table->string('status')->default('new'); // 'new', 'processed', 'ignored'
            // Unique match key: "p:{postId}:k:{keywordId}" or "c:{commentId}:k:{keywordId}"
            $table->string('dedupe_key', 80)->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['group_id', 'status']);
            $table->index(['platform', 'source_entity_type', 'source_entity_id']);
            $table->index(['platform', 'channel_or_group_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
