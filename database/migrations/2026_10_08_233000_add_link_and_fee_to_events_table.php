<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'link')) {
                $table->string('link', 500)->nullable()->after('image');
            }
            if (!Schema::hasColumn('events', 'fee')) {
                $table->string('fee', 50)->nullable()->after('link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'link')) {
                $table->dropColumn('link');
            }
            if (Schema::hasColumn('events', 'fee')) {
                $table->dropColumn('fee');
            }
        });
    }
};
