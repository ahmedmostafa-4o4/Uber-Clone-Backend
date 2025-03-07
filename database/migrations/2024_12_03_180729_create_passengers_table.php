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
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique()->index()->nullable();
            $table->string('name');
            $table->string('phone_number');
            $table->string('email');
            $table->string('address')->nullable();
            $table->json('rating')->default(json_encode(["rate" => 0, "rate_count" => 0]));
            $table->json('saved_payment_methods')->default(json_encode(['default' => null, 'methods' => []])); // Ensure default empty array
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};
