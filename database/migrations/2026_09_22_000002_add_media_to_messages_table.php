<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->string('message_type')->default('text')->after('content'); // 'text', 'image', 'audio'
            $table->longText('image')->nullable()->after('message_type');
            $table->longText('audio')->nullable()->after('image');
            $table->unsignedInteger('audio_duration')->nullable()->after('audio'); // seconds
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
            $table->dropColumn(['message_type', 'image', 'audio', 'audio_duration']);
        });
    }
};
