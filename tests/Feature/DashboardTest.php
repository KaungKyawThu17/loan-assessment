<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users are redirected to the dashboard for their role', function (string $role, string $destination) {
    $user = User::factory()->create([
        'role' => $role,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($destination));
})->with([
    'customer' => ['customer', 'customer.loans.index'],
    'officer' => ['loan_officer', 'staff.loans.index'],
    'admin' => ['admin', 'admin.dashboard'],
]);
