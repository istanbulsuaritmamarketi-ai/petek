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
        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->dateTime('ended_at')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('duration_minutes');
            $table->boolean('failed')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'failed']);
        });
    }
};
