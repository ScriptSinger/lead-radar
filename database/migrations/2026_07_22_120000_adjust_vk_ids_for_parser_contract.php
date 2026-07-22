<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parser returns composite wall ids like "-151103485_1636363".
 * Comment ids are numeric per wall; uniqueness is (post_id, vk_comment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE vk_posts MODIFY vk_post_id VARCHAR(64) NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot easily MODIFY; recreate if needed is heavy —
            // for sqlite tests, string storage of integers still works as text affinity.
            // Skip structural change when already flexible.
        } else {
            Schema::table('vk_posts', function (Blueprint $table) {
                $table->string('vk_post_id', 64)->change();
            });
        }

        Schema::table('vk_comments', function (Blueprint $table) {
            $table->dropUnique(['vk_comment_id']);
        });

        Schema::table('vk_comments', function (Blueprint $table) {
            $table->unique(['post_id', 'vk_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vk_comments', function (Blueprint $table) {
            $table->dropUnique(['post_id', 'vk_comment_id']);
        });

        Schema::table('vk_comments', function (Blueprint $table) {
            $table->unique(['vk_comment_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE vk_posts MODIFY vk_post_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
