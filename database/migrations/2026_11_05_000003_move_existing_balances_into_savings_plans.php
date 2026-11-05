<?php

use App\Models\SavingsPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every existing customer a "General savings" plan holding their current balance.
     */
    public function up(): void
    {
        SavingsPlan::adoptCustomersWithoutPlans();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transactions')->where('type', 'commission')->delete();
        DB::table('transactions')->update(['savings_plan_id' => null]);
        DB::table('savings_plans')->delete();
    }
};
