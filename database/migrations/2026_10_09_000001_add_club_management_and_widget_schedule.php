<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('users', 'club_category')) {
                $table->string('club_category', 100)->nullable()->after('club_description');
            }
            if (!Schema::hasColumn('users', 'course_schedule')) {
                $table->json('course_schedule')->nullable()->after('club_category');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'is_club_event')) {
                $table->boolean('is_club_event')->default(false)->after('fee')->index();
            }
            if (!Schema::hasColumn('events', 'club_id')) {
                $table->unsignedBigInteger('club_id')->nullable()->after('is_club_event')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'owner_id')) {
                $table->dropColumn('owner_id');
            }
            if (Schema::hasColumn('users', 'club_category')) {
                $table->dropColumn('club_category');
            }
            if (Schema::hasColumn('users', 'course_schedule')) {
                $table->dropColumn('course_schedule');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'is_club_event')) {
                $table->dropColumn('is_club_event');
            }
            if (Schema::hasColumn('events', 'club_id')) {
                $table->dropColumn('club_id');
            }
        });
    }
};
