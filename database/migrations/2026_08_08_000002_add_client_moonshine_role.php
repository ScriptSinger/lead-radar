<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('moonshine_user_roles')->where('name', 'Client')->exists()) {
            return;
        }

        DB::table('moonshine_user_roles')->insert([
            'name' => 'Client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('moonshine_user_roles')->where('name', 'Client')->delete();
    }
};
