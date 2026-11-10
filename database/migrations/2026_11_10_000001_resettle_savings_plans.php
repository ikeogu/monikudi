<?php

use App\Models\SavingsPlan;
use App\Services\Ledger;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Replay every counting plan under the current rules: 31-day pages, a leftover under a day
     * staying on its full page, and plans that reached their target marked completed.
     */
    public function up(): void
    {
        $ledger = app(Ledger::class);

        SavingsPlan::whereNotNull('daily_amount')->each(fn (SavingsPlan $plan) => $ledger->resettle($plan));
    }

    /**
     * Nothing to undo: the replay only brings stored figures in line with the entries.
     */
    public function down(): void {}
};
