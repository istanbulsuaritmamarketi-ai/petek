<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deals')) {
            Schema::create('deals', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('provider');
                $table->string('category')->default('software');
                $table->string('category_label')->nullable();
                $table->string('discount');
                $table->text('description')->nullable();
                $table->string('promo_code')->nullable();
                $table->string('url');
                $table->string('icon')->default('pricetag');
                $table->string('color')->default('#FDB913');
                $table->boolean('verified')->default(true);
                $table->string('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('quick_links')) {
            Schema::create('quick_links', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category')->default('akademi');
                $table->string('category_label')->nullable();
                $table->text('description')->nullable();
                $table->string('url');
                $table->string('icon')->default('link');
                $table->string('color')->default('#FDB913');
                $table->string('badge')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('quick_links');
    }
};
