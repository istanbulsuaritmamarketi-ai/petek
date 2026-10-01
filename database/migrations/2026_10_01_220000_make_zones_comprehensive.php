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
        Schema::table('zones', function (Blueprint $table) {
            if (!Schema::hasColumn('zones', 'campus')) {
                $table->string('campus')->default('Ayazağa')->after('name');
            }
            if (!Schema::hasColumn('zones', 'building')) {
                $table->string('building')->nullable()->after('campus');
            }
            if (!Schema::hasColumn('zones', 'floor')) {
                $table->string('floor')->nullable()->after('building');
            }
            if (!Schema::hasColumn('zones', 'description')) {
                $table->text('description')->nullable()->after('floor');
            }
            if (!Schema::hasColumn('zones', 'directions')) {
                $table->text('directions')->nullable()->after('description');
            }
            if (!Schema::hasColumn('zones', 'map_url')) {
                $table->string('map_url')->nullable()->after('directions');
            }
            if (!Schema::hasColumn('zones', 'opening_hours')) {
                $table->string('opening_hours')->nullable()->after('map_url');
            }
            if (!Schema::hasColumn('zones', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('opening_hours');
            }
            if (!Schema::hasColumn('zones', 'phone')) {
                $table->string('phone')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('zones', 'image')) {
                $table->string('image')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $columns = [
                'campus',
                'building',
                'floor',
                'description',
                'directions',
                'map_url',
                'opening_hours',
                'is_active',
                'phone',
                'image',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('zones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
