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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
            $table->foreignId('created_by')->nullable()->after('wallet_balance')->constrained('users')->nullOnDelete();
            $table->index('reg_no');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['reg_no']);
            $table->dropIndex(['phone']);
            $table->dropColumn('address');
        });
    }
};
