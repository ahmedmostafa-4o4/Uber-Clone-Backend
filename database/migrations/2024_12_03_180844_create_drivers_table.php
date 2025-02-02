<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_number');
            $table->string('email');
            $table->string('password');
            $table->string('address')->nullable();
            $table->string('license_number');
            $table->json('rating')->default(json_encode(["rate" => 0, "rate_count" => 0]));
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->float('driving_experience');
            $table->string('car_model');
            $table->string('license_plate');
            $table->string('car_color');
            $table->string('manufacturing_year');
            $table->json('insurance_info');
            $table->json('registration_info');
            $table->enum('is_verified', [0, 1, 'pending'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
