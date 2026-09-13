<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;

test('each role can access only its intended loan areas', function (string $role, bool $admin, bool $staff, bool $customerArea) {
    $customer = Customer::factory()->create();
    $user = $customer->user;
    $user->role = $role;

    expect($user->can('access-admin-area'))->toBe($admin);
    expect($user->can('access-staff-area'))->toBe($staff);
    expect($user->can('access-customer-area'))->toBe($customerArea);
    expect($user->can('viewAny', LoanApplication::class))->toBe($admin || $staff || $customerArea);
})->with([
    'admin' => ['admin', true, true, false],
    'officer' => ['loan_officer', false, true, false],
    'customer' => ['customer', false, false, true],
    'unknown role' => ['unknown', false, false, false],
]);

test('customers control only their own profile and pending applications', function () {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $pendingLoan = LoanApplication::factory()->for($customer)->create();
    $approvedLoan = LoanApplication::factory()->for($customer)->create(['status' => 'approved']);
    $otherLoan = LoanApplication::factory()->for($otherCustomer)->create();

    expect($customer->user->can('view', $customer))->toBeTrue();
    expect($customer->user->can('update', $customer))->toBeTrue();
    expect($customer->user->can('view', $otherCustomer))->toBeFalse();
    expect($customer->user->can('update', $otherCustomer))->toBeFalse();
    expect($customer->user->can('create', LoanApplication::class))->toBeTrue();

    foreach (['update', 'cancel'] as $ability) {
        expect($customer->user->can($ability, $pendingLoan))->toBeTrue();
        expect($customer->user->can($ability, $approvedLoan))->toBeFalse();
        expect($customer->user->can($ability, $otherLoan))->toBeFalse();
    }
});

test('officers see customer profiles only while assigned to one of their loans', function () {
    $officer = User::factory()->create(['role' => 'loan_officer']);
    $loan = LoanApplication::factory()->create(['assigned_reviewer_id' => $officer->id]);
    $otherCustomer = Customer::factory()->create();

    expect($officer->can('view', $loan->customer))->toBeTrue();
    expect($officer->can('view', $otherCustomer))->toBeFalse();
    expect($officer->can('update', $loan->customer))->toBeFalse();

    $loan->assigned_reviewer_id = null;
    $loan->save();

    expect($officer->can('view', $loan->customer))->toBeFalse();
});

test('unimplemented customer and loan operations remain forbidden for every role', function (string $role) {
    $customer = Customer::factory()->create();
    $loan = LoanApplication::factory()->for($customer)->create();
    $user = $customer->user;
    $user->role = $role;

    expect($user->can('viewAny', Customer::class))->toBeFalse();
    expect($user->can('create', Customer::class))->toBeFalse();

    foreach (['delete', 'restore', 'forceDelete'] as $ability) {
        expect($user->can($ability, $customer))->toBeFalse();
        expect($user->can($ability, $loan))->toBeFalse();
    }
})->with(['admin', 'loan_officer', 'customer']);

test('an unknown role cannot enter the dashboard', function () {
    $user = User::factory()->create();
    $user->role = 'unknown';

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});
