<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quick_links') && !Schema::hasColumn('quick_links', 'image')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->string('image', 1000)->nullable()->after('icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quick_links') && Schema::hasColumn('quick_links', 'image')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
