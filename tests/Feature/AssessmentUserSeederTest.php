<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\AssessmentUserSeeder;
use Illuminate\Support\Facades\Hash;

test('demo accounts can be seeded repeatedly without duplicate users or profiles', function () {
    $this->seed(AssessmentUserSeeder::class);
    $firstUserIds = User::query()->orderBy('id')->pluck('id')->all();
    $firstCustomerIds = Customer::query()->orderBy('id')->pluck('id')->all();

    $this->seed(AssessmentUserSeeder::class);

    $this->assertDatabaseCount('users', 4);
    $this->assertDatabaseCount('customers', 2);
    expect(User::query()->orderBy('id')->pluck('id')->all())->toBe($firstUserIds);
    expect(Customer::query()->orderBy('id')->pluck('id')->all())->toBe($firstCustomerIds);

    $admin = User::query()->where('email', 'admin@loan.test')->sole();
    $officer = User::query()->where('email', 'officer@loan.test')->sole();
    $customer = User::query()->where('email', 'customer1@loan.test')->sole();

    expect($admin->can('access-admin-area'))->toBeTrue();
    expect($officer->can('access-staff-area'))->toBeTrue();
    expect($customer->can('access-customer-area'))->toBeTrue();
    expect(Hash::check('LocalDemo!2026', $customer->password))->toBeTrue();
    expect($customer->email_verified_at)->not->toBeNull();
    expect($customer->customer->email)->toBe($customer->email);
});

test('demo accounts cannot be seeded in production', function () {
    $this->app->instance('env', 'production');

    try {
        expect(fn () => (new AssessmentUserSeeder)->run())
            ->toThrow(RuntimeException::class, 'Demo accounts can only be seeded locally or in tests.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    } finally {
        $this->app->instance('env', 'testing');
    }
});
