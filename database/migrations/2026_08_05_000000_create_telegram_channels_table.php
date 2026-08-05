<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_channels', function (Blueprint $table) {
            $table->id();
            // Canonical public address, e.g. https://t.me/example_channel.
            $table->string('url')->unique();
            $table->string('username')->nullable()->unique();
            // MTProto peer data; access_hash is required to read a resolved channel.
            $table->unsignedBigInteger('telegram_channel_id')->nullable()->unique();
            $table->string('access_hash', 32)->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'last_scan_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_channels');
    }
};
