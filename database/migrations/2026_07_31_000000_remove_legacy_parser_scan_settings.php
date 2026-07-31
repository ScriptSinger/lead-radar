<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('scan_settings', 'paused_until') ? 'paused_until' : null,
            Schema::hasColumn('scan_settings', 'pause_reason') ? 'pause_reason' : null,
        ]));

        if ($columns !== []) {
            Schema::table('scan_settings', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('scan_settings', function (Blueprint $table) {
            $table->timestamp('paused_until')->nullable();
            $table->string('pause_reason', 255)->nullable();
        });
    }
};
