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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->cascadeOnDelete();
            $table->decimal('passenger_rating', 2, 1)->default(0); // Change precision to 2 to allow for fractional values like 4.5
            $table->decimal('driver_rating', 2, 1)->default(0);    // Same as above
            $table->text('comments')->nullable();                 // Nullable in case there are no comments
            $table->text('issues_reported')->nullable();          // Nullable for rides without issues
            $table->enum('resolution_status', ['pending', 'resolved', 'dismissed'])->default('pending'); // Constrain values
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
