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
        if (DB::getSchemaBuilder()->hasTable('quick_links')) {
            DB::table('quick_links')
                ->where('url', 'like', '%kart.itu.edu.tr%')
                ->orWhere('name', 'like', '%Kart Bakiye%')
                ->update(['url' => 'https://kampuskart.itu.edu.tr/User/Login']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('quick_links')) {
            DB::table('quick_links')
                ->where('url', 'https://kampuskart.itu.edu.tr/User/Login')
                ->update(['url' => 'https://kart.itu.edu.tr']);
        }
    }
};
