<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'dismissed_notifications')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('dismissed_notifications')->nullable()->after('badges');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'dismissed_notifications')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dismissed_notifications');
            });
        }
    }
};
