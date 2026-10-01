<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_floors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('floor_code')->unique();
            $table->string('building')->default('Mustafa İnan Kütüphanesi');
            $table->unsignedInteger('capacity')->default(250);
            $table->unsignedInteger('occupancy_rate')->default(45);
            $table->string('socket_status')->default('plentiful');
            $table->string('noise_level')->default('silent');
            $table->string('wifi_quality')->default('Eduroam Güçlü');
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('library_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained('library_floors')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('occupancy_level')->default('medium');
            $table->string('socket_level')->default('plentiful');
            $table->string('noise_level')->default('silent');
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->index(['floor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reports');
        Schema::dropIfExists('library_floors');
    }
};
