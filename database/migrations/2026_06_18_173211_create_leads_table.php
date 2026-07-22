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
            $table->string('source_type'); // 'post' or 'comment'
            $table->foreignId('post_id')->constrained('vk_posts')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('vk_comments')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('vk_groups')->cascadeOnDelete();
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
