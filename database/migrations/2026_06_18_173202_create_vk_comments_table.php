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
        Schema::create('vk_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('vk_posts')->cascadeOnDelete();
            // VK reply id; unique together with post (not globally)
            $table->unsignedBigInteger('vk_comment_id');
            $table->unsignedBigInteger('parent_comment_id')->nullable();
            $table->unique(['post_id', 'vk_comment_id']);
            $table->text('text');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vk_comments');
    }
};
