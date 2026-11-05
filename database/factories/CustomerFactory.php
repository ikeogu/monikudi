<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SavingsPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '080'.fake()->unique()->numerify('########'),
            'reg_no' => 'AJ'.str_pad(fake()->unique()->numberBetween(1, 99999), 7, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Every customer has a plan, as when registered through the app. A balance given with
     * withBalance() has no entries behind it, so it's carried in as the plan's opening balance.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Customer $customer) {
            if (! $customer->plans()->exists()) {
                SavingsPlan::factory()->for($customer)->create()->forceFill([
                    'balance' => $customer->balance,
                    'opening_balance' => (int) $customer->wallet_balance,
                ])->save();
            }
        });
    }

    /**
     * A customer who already has money saved (kobo).
     */
    public function withBalance(float $naira): static
    {
        return $this->state(fn () => ['wallet_balance' => (int) round($naira * 100)]);
    }
}
