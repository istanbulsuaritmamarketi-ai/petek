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
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Ensure Admin (IDs 1 & 3) and any existing admin role accounts are marked email verified
        \Illuminate\Support\Facades\DB::table('users')
            ->whereIn('id', [1, 3])
            ->orWhere('role', 'admin')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
