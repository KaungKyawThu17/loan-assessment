<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
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
            'user_id' => User::factory()->state(['role' => 'customer']),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09123456789',
            'address' => 'Yangon',
        ];
    }
}
