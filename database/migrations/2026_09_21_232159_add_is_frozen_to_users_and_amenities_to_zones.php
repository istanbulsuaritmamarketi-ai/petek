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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_frozen')) {
                $table->boolean('is_frozen')->default(false)->after('role');
            }
        });

        Schema::table('zones', function (Blueprint $table) {
            if (!Schema::hasColumn('zones', 'capacity')) {
                $table->integer('capacity')->default(200)->after('radius');
            }
            if (!Schema::hasColumn('zones', 'socket_status')) {
                $table->string('socket_status')->default('bol')->after('capacity'); // bol, kisitli, yok
            }
            if (!Schema::hasColumn('zones', 'noise_level')) {
                $table->string('noise_level')->default('orta')->after('socket_status'); // sessiz, orta, serbest
            }
            if (!Schema::hasColumn('zones', 'wifi_quality')) {
                $table->string('wifi_quality')->default('mukemmel')->after('noise_level'); // mukemmel, orta, zayif
            }
            if (!Schema::hasColumn('zones', 'amenities')) {
                $table->json('amenities')->nullable()->after('wifi_quality');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_frozen')) {
                $table->dropColumn('is_frozen');
            }
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'socket_status', 'noise_level', 'wifi_quality', 'amenities']);
        });
    }
};
