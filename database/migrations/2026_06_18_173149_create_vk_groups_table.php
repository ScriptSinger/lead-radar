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
        Schema::create('vk_groups', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vk_groups');
    }
};
