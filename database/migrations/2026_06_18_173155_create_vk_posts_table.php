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
        Schema::create('vk_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('vk_groups')->cascadeOnDelete();
            // Composite wall id from VK, e.g. "-151103485_1636363"
            $table->string('vk_post_id', 64)->unique();
            $table->text('text')->nullable();
            $table->string('url');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vk_posts');
    }
};
