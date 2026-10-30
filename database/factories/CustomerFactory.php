<?php

namespace Database\Factories;

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
     * A customer who already has money saved (kobo).
     */
    public function withBalance(float $naira): static
    {
        return $this->state(fn () => ['wallet_balance' => (int) round($naira * 100)]);
    }
}
