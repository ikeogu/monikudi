<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample staff, customers and a month of contributions for local development:
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(Ledger $ledger): void
    {
        $this->call(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@monikudi.com')->first();
        $collectors = User::factory()->count(3)->collector()->create()->push($admin);

        // Post a month and a half of contributions through the ledger, back-dated day by day.
        $customers = Customer::factory()->count(40)
            ->sequence(fn ($sequence) => ['reg_no' => 'AJ'.str_pad($sequence->index + 101, 7, '0', STR_PAD_LEFT)])
            ->create(['created_by' => $admin->id]);

        $today = Carbon::today();

        $customers->each(function (Customer $customer) use ($ledger, $collectors, $admin, $today) {
            $daily = fake()->randomElement([200, 500, 1000, 2000]);

            for ($day = 45; $day >= 0; $day--) {
                if (fake()->boolean(25)) {
                    continue;
                }

                $collector = $collectors->random();
                Carbon::setTestNow($today->copy()->subDays($day)->setTime(rand(8, 17), rand(0, 59)));

                $ledger->record($customer, [
                    'type' => Transaction::TYPE_DEPOSIT,
                    'amount' => $daily,
                    'date' => today()->toDateString(),
                    'payment_method' => fake()->randomElement(['cash', 'cash', 'cash', 'transfer']),
                    'collected_by' => $collector->id,
                    'description' => 'Daily contribution',
                ], $collector);

                $withdrawal = min($daily * rand(2, 5), $customer->refresh()->balance);
                if ($day % 14 === 0 && $withdrawal >= 1 && fake()->boolean(40)) {
                    $ledger->record($customer, [
                        'type' => Transaction::TYPE_WITHDRAWAL,
                        'amount' => $withdrawal,
                        'date' => today()->toDateString(),
                        'payment_method' => 'cash',
                        'collected_by' => $admin->id,
                        'description' => 'Withdrawal',
                    ], $admin);
                }
            }
        });

        Carbon::setTestNow();
    }
}
