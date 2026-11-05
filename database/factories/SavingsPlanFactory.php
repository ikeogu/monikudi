<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Plans start without a daily amount, like those moved over from before plans existed,
 * so they take no commission. Use daily() for a plan whose pages take commission.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavingsPlan>
 */
class SavingsPlanFactory extends Factory
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
            'name' => 'Daily savings',
            'daily_amount' => null,
        ];
    }

    public function daily(float $naira): static
    {
        return $this->state(fn () => ['daily_amount' => $naira]);
    }
}
