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
        Schema::create('broadcast_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->text('message');
            $table->string('type', 50)->default('announcement');
            $table->string('target_screen', 100)->nullable();
            $table->string('target_url', 255)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_notifications');
    }
};
