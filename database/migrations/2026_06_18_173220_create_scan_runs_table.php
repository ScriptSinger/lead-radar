<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('vk_groups')->nullOnDelete();
            $table->string('trigger', 32)->default('manual'); // manual|schedule|job|admin
            $table->string('status', 32)->default('running'); // running|success|failed|parser_down
            $table->boolean('with_comments')->default(false);
            $table->unsignedSmallInteger('limit')->default(6);
            $table->unsignedInteger('posts_fetched')->default(0);
            $table->unsignedInteger('posts_created')->default(0);
            $table->unsignedInteger('posts_updated')->default(0);
            $table->unsignedInteger('comments_fetched')->default(0);
            $table->unsignedInteger('comments_created')->default(0);
            $table->unsignedInteger('comments_updated')->default(0);
            $table->unsignedInteger('leads_created')->default(0);
            $table->unsignedInteger('leads_updated')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('stats')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['group_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_runs');
    }
};
