<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '09123456789',
        'address' => 'Yangon',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->sole();
    expect($user->role)->toBe('customer');
    expect(Hash::check('password', $user->password))->toBeTrue();
    $this->assertDatabaseHas('customers', [
        'user_id' => $user->id,
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '09123456789',
        'address' => 'Yangon',
    ]);
});

test('invalid registration data does not create a user or customer', function (array $overrides, string $field) {
    $response = $this->post(route('register.store'), array_replace([
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '09123456789',
        'address' => 'Yangon',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides));

    $response->assertSessionHasErrors($field);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('customers', 0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'invalid'], 'email'],
    'invalid phone' => [['phone' => 'phone'], 'phone'],
    'missing address' => [['address' => ''], 'address'],
    'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
]);
