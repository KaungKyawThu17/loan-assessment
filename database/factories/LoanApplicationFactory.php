<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanApplication>
 */
class LoanApplicationFactory extends Factory
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
            'amount' => '1000000.00',
            'term_months' => 12,
            'interest_rate' => '10.00',
            'status' => 'pending',
            'application_date' => now()->toDateString(),
            'approved_at' => null,
            'purpose' => 'Equipment purchase',
            'supporting_notes' => null,
            'assigned_reviewer_id' => null,
            'decision_notes' => null,
        ];
    }
}
