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
        Schema::table('transactions', function (Blueprint $table) {
            // An enum only allowed credit/debit; commission entries need a third type.
            $table->string('type', 20)->change();

            $table->foreignUlid('savings_plan_id')->nullable()->after('customer_id')->constrained()->cascadeOnDelete();
            // Entries from before plans existed never start a page or attract commission.
            $table->boolean('commission_exempt')->default(false)->after('type');
            // Worked out by the ledger: which monthly page an entry is on and how many days it holds.
            $table->unsignedInteger('page_number')->nullable()->after('commission_exempt');
            $table->unsignedTinyInteger('page_day')->nullable()->after('page_number');
            // On a commission entry: the deposit whose payment started the page.
            $table->ulid('source_id')->nullable()->after('page_day')->index();
            $table->index(['savings_plan_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['savings_plan_id', 'type']);
            $table->dropConstrainedForeignId('savings_plan_id');
            $table->dropIndex(['source_id']);
            $table->dropColumn(['commission_exempt', 'page_number', 'page_day', 'source_id']);
            $table->enum('type', ['credit', 'debit'])->change();
        });
    }
};
