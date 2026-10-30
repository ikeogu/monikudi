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
        // The model already listed payment_method as fillable, so guard against a manually added column.
        $hasPaymentMethod = Schema::hasColumn('transactions', 'payment_method');

        Schema::table('transactions', function (Blueprint $table) use ($hasPaymentMethod) {
            if (! $hasPaymentMethod) {
                $table->string('payment_method')->default('cash')->after('amount');
            }
            $table->unsignedBigInteger('balance_after')->nullable()->after('amount');
            $table->foreignId('collected_by')->nullable()->after('date')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('collected_by')->constrained('users')->nullOnDelete();
            $table->index(['date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['date', 'type']);
            $table->dropColumn(['balance_after', 'payment_method']);
        });
    }
};
