<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_settings', function (Blueprint $table) {
            $table->id();
            // Singleton profile name (v1: always "default")
            $table->string('name', 64)->default('default')->unique();
            $table->boolean('schedule_enabled')->default(true);
            // How often the scheduler may start a new fan-out wave (minutes)
            $table->unsignedSmallInteger('interval_minutes')->default(30);
            // Stagger between ScanVkGroupJob dispatches (seconds)
            $table->unsignedSmallInteger('group_delay_seconds')->default(50);
            // Top-N wall posts from parser (1–30)
            $table->unsignedTinyInteger('scan_limit')->default(8);
            $table->boolean('with_comments')->default(true);
            // since_last_scan | today | all
            $table->string('post_window', 32)->default('since_last_scan');
            $table->timestamp('last_dispatched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_settings');
    }
};
