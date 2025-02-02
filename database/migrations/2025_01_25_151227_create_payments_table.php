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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->onDelete('cascade');
            $table->string('payment_method')->nullable();
            $table->json('payment_method_detailes')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->string('currency')->nullable();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2);
            $table->json('metadata')->nullable();
            $table->string('failure_reason')->nullable();
            $table->boolean('captured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
