<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_scan_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->default('default');
            $table->boolean('schedule_enabled')->default(false);
            $table->unsignedSmallInteger('interval_minutes')->default(30);
            $table->unsignedSmallInteger('channel_delay_seconds')->default(3);
            $table->unsignedSmallInteger('scan_limit')->default(20);
            $table->boolean('with_comments')->default(true);
            $table->unsignedSmallInteger('comments_limit')->default(100);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_scan_settings');
    }
};
