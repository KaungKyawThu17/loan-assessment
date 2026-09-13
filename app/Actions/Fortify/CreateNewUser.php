<?php

namespace App\Actions\Fortify;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],

            'password' => ['required', 'string', Password::default(), 'confirmed'],

            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/\A\+?[0-9]{7,15}\z/',
            ],

            'address' => [
                'required',
                'string',
                'max:1000',
            ],
        ], [
            'phone.regex' => 'Enter 7–15 digits',
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = new User;

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->password = $validated['password'];
            $user->role = 'customer';
            $user->save();

            $customer = new Customer;
            $customer->name = $validated['name'];
            $customer->email = $validated['email'];
            $customer->phone = $validated['phone'];
            $customer->address = $validated['address'];

            $user->customer()->save($customer);

            return $user;
        });
    }
}
