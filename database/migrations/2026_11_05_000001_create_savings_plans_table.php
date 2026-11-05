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
        Schema::create('savings_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            // Kobo. Empty only on plans moved over from before plans existed; those take no commission.
            $table->unsignedBigInteger('daily_amount')->nullable();
            $table->unsignedSmallInteger('target_months')->nullable();
            $table->unsignedBigInteger('balance')->default(0);
            // Kobo carried into a plan that its entries don't account for (balances from older
            // app versions), so the ledger never silently changes a customer's balance.
            $table->bigInteger('opening_balance')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_plans');
    }
};
