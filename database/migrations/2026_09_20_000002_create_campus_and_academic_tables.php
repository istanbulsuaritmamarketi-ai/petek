<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campus Zones (Library, Canteen, Gym, etc.)
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->integer('radius')->default(100); // meters
            $table->enum('zone_type', ['library', 'canteen', 'gym', 'event_hall', 'techpark'])->default('canteen');
            $table->boolean('has_menu')->default(false);
            $table->json('price_list')->nullable();
            $table->timestamps();
        });

        // Zone Density Votes
        Schema::create('zone_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('density_level', ['low', 'medium', 'high', 'very_high'])->default('medium');
            $table->timestamps();

            $table->index(['zone_id', 'created_at']);
        });

        // Professors (Hocametre)
        Schema::create('professors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department');
            $table->timestamps();

            $table->index(['name']);
            $table->index(['department']);
        });

        // Professor Ratings
        Schema::create('professor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professor_id')->constrained('professors')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('attendance')->default(3); // 1-5
            $table->integer('difficulty')->default(3); // 1-5
            $table->integer('teaching')->default(3);   // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['professor_id', 'user_id']);
        });

        // Events Calendar
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->dateTime('event_date');
            $table->string('location');
            $table->string('organizer_club');
            $table->text('image')->nullable();
            $table->timestamps();

            $table->index(['event_date']);
        });

        // Focus Sessions (Pomodoro & Faculty Wars)
        Schema::create('focus_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('set null');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->integer('duration_minutes');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_sessions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('professor_ratings');
        Schema::dropIfExists('professors');
        Schema::dropIfExists('zone_votes');
        Schema::dropIfExists('zones');
    }
};
