<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Ledger;
use Illuminate\Console\Command;

class LedgerRebuild extends Command
{
    protected $signature = 'ledger:rebuild {--dry-run : Only report customers whose balance does not match their entries}';

    protected $description = 'Recalculate customer balances from their deposits and withdrawals';

    public function handle(Ledger $ledger): int
    {
        $save = ! $this->option('dry-run');
        $rows = [];

        Customer::withTrashed()->orderBy('reg_no')->each(function (Customer $customer) use ($ledger, $save, &$rows) {
            [$stored, $calculated] = $ledger->rebuild($customer, $save);

            if ($stored !== $calculated) {
                $rows[] = [$customer->reg_no, $customer->full_name, number_format($stored / 100, 2), number_format($calculated / 100, 2)];
            }
        });

        if ($rows === []) {
            $this->info('All balances match their entries.');

            return self::SUCCESS;
        }

        $this->table(['Customer ID', 'Name', 'Stored balance', 'From entries'], $rows);
        $this->line($save
            ? count($rows).' balance(s) corrected. Negative results were floored at 0; check those customers.'
            : count($rows).' mismatch(es) found. Run without --dry-run to fix them.');

        return self::SUCCESS;
    }
}
