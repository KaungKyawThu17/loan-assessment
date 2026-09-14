<?php

use App\Models\User;

test('home page shows the login form to guests', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('action="'.route('login.store').'"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false);
});

test('home page redirects authenticated users to their dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
