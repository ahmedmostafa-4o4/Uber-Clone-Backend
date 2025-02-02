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
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // Can store email, phone, or user-related identifier
            $table->integer('otp_code'); // OTP code (e.g., 6-digit)
            $table->timestamp('expires_at'); // Expiration timestamp
            $table->boolean('is_used')->default(false); // Whether OTP is already used
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
