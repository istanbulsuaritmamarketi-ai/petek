<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_menus', function (Blueprint $table) {
            $table->timestamp('last_notified_at')->nullable()->after('notification_sent');
            $table->string('notification_title')->nullable()->after('last_notified_at');
            $table->string('notification_body', 500)->nullable()->after('notification_title');
            $table->unsignedInteger('notification_count')->default(0)->after('notification_body');
        });
    }

    public function down(): void
    {
        Schema::table('dining_menus', function (Blueprint $table) {
            $table->dropColumn(['last_notified_at', 'notification_title', 'notification_body', 'notification_count']);
        });
    }
};
