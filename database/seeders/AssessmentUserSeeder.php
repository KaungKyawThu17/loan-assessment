<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentUserSeeder extends Seeder
{
    public function run(): void
    {
        // These accounts are for local development and testing.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Demo accounts can only be seeded locally or in tests.'
            );
        }

        $accounts = [
            [
                'name' => 'Demo Admin',
                'email' => 'admin@loan.test',
                'role' => 'admin',
            ],
            [
                'name' => 'Demo Officer',
                'email' => 'officer@loan.test',
                'role' => 'loan_officer',
            ],
            [
                'name' => 'Customer One',
                'email' => 'customer1@loan.test',
                'role' => 'customer',
            ],
            [
                'name' => 'Customer Two',
                'email' => 'customer2@loan.test',
                'role' => 'customer',
            ],
        ];

        foreach ($accounts as $account) {
            DB::transaction(function () use ($account): void {
                $user = User::firstOrNew([
                    'email' => $account['email'],
                ]);

                $user->name = $account['name'];
                $user->role = $account['role'];
                $user->password = 'LocalDemo!2026';
                $user->email_verified_at = now();

                $user->save();

                if ($user->role === 'customer') {
                    $customer = $user->customer()->firstOrNew();

                    $customer->name = $user->name;
                    $customer->email = $user->email;
                    $customer->phone = '09123456789';
                    $customer->address = 'Yangon, Myanmar';

                    $user->customer()->save($customer);
                }
            });
        }
    }
}
