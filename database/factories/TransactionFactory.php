<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates raw entries without touching balances; use App\Services\Ledger for real postings.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => fake()->randomElement([500, 1000, 2000]),
            'payment_method' => 'cash',
            'date' => today()->toDateString(),
            'description' => 'Daily contribution',
        ];
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_WITHDRAWAL]);
    }
}
