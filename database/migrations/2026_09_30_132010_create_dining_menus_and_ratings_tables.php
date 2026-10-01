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
        Schema::create('dining_menus', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('meal_type')->default('lunch'); // lunch, dinner
            $table->string('title')->nullable();
            $table->json('items'); // ["Ezogelin Çorbası", "Orman Kebabı", "Pirinç Pilavı", "Mevsim Salata"]
            $table->string('calories')->nullable(); // e.g. "860 kcal"
            $table->json('vegetarian_items')->nullable();
            $table->unsignedInteger('rating_sum')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();

            $table->unique(['date', 'meal_type']);
            $table->index(['date']);
        });

        Schema::create('dining_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_menu_id')->constrained('dining_menus')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('score')->default(5); // 1 to 5
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->unique(['dining_menu_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_ratings');
        Schema::dropIfExists('dining_menus');
    }
};
