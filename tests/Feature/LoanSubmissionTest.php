<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;
use Livewire\Livewire;

test('customers can submit a loan with optional supporting notes', function (string $notes, ?string $savedNotes) {
    $this->freezeTime();
    config(['loans.annual_interest_rate' => '12.50']);
    $customer = Customer::factory()->create();

    $page = Livewire::actingAs($customer->user)
        ->test('pages::customer.loans.create')
        ->set('amount', '250000.50')
        ->set('term_months', '6')
        ->set('purpose', '  Shop equipment  ')
        ->set('supporting_notes', $notes)
        ->call('submit')
        ->assertHasNoErrors();

    $loan = $customer->loanApplications()->sole();
    $page->assertRedirectToRoute('customer.loans.show', ['loan' => $loan->id]);
    expect($loan->application_date->toDateString())->toBe(now()->toDateString());

    $this->assertDatabaseHas('loan_applications', [
        'id' => $loan->id,
        'customer_id' => $customer->id,
        'amount' => '250000.50',
        'term_months' => 6,
        'interest_rate' => '12.50',
        'purpose' => 'Shop equipment',
        'supporting_notes' => $savedNotes,
        'status' => 'pending',
        'assigned_reviewer_id' => null,
        'decision_notes' => null,
        'approved_at' => null,
    ]);
})->with([
    'provided notes' => ['  Receipts are available.  ', 'Receipts are available.'],
    'empty notes' => ['', null],
    'whitespace notes' => ['   ', null],
]);

test('a second pending application is refused without losing the form input', function () {
    $customer = Customer::factory()->create();
    LoanApplication::factory()->for($customer)->create();

    Livewire::actingAs($customer->user)
        ->test('pages::customer.loans.create')
        ->set('amount', '250000')
        ->set('purpose', 'Shop equipment')
        ->set('supporting_notes', 'Keep these notes.')
        ->call('submit')
        ->assertHasErrors(['application'])
        ->assertSee('You already have a pending loan application.')
        ->assertSet('purpose', 'Shop equipment')
        ->assertSet('supporting_notes', 'Keep these notes.');

    $this->assertDatabaseCount('loan_applications', 1);
});

test('completed loans and another customer pending loan do not prevent submission', function () {
    $customer = Customer::factory()->create();
    LoanApplication::factory()->for($customer)->create(['status' => 'rejected']);
    LoanApplication::factory()->create();

    Livewire::actingAs($customer->user)
        ->test('pages::customer.loans.create')
        ->set('amount', '100000')
        ->set('term_months', '3')
        ->set('purpose', 'New equipment')
        ->call('submit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('loan_applications', [
        'customer_id' => $customer->id,
        'status' => 'pending',
        'purpose' => 'New equipment',
    ]);
    $this->assertDatabaseCount('loan_applications', 3);
});

test('invalid loan input is refused before saving', function (string $field, string $value, string $rule) {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test('pages::customer.loans.create')
        ->set('amount', '100000')
        ->set('term_months', '12')
        ->set('purpose', 'Shop equipment')
        ->set($field, $value)
        ->call('submit')
        ->assertHasErrors([$field => $rule])
        ->assertSet($field, $value);

    $this->assertDatabaseCount('loan_applications', 0);
})->with([
    'minimum amount' => ['amount', '99999', 'min'],
    'maximum amount' => ['amount', '10000001', 'max'],
    'amount precision' => ['amount', '100000.001', 'decimal'],
    'whole months' => ['term_months', '3.5', 'integer'],
    'minimum term' => ['term_months', '2', 'between'],
    'maximum term' => ['term_months', '25', 'between'],
    'required purpose' => ['purpose', '', 'required'],
    'purpose length' => ['purpose', str_repeat('a', 256), 'max'],
    'notes length' => ['supporting_notes', str_repeat('a', 2001), 'max'],
]);

test('only a customer with a profile can open the loan application form', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('customer.loans.create'))
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test('pages::customer.loans.create')
        ->assertForbidden();

    $this->assertDatabaseCount('loan_applications', 0);
})->with(['admin', 'loan_officer', 'customer']);

test('guests must log in before applying for a loan', function () {
    $this->get(route('customer.loans.create'))->assertRedirectToRoute('login');
});

test('customers can read only their own applications and repayment details', function () {
    $customer = Customer::factory()->create();
    $ownLoan = LoanApplication::factory()->for($customer)->create(['purpose' => 'My shop equipment']);
    $otherLoan = LoanApplication::factory()->create(['purpose' => 'Private business expansion']);

    $this->actingAs($customer->user)
        ->get(route('customer.loans.index'))
        ->assertSee('My shop equipment')
        ->assertDontSee('Private business expansion');

    $this->get(route('customer.loans.show', ['loan' => $ownLoan->id]))
        ->assertSee('My shop equipment')
        ->assertSee('91,666.63');

    $this->get(route('customer.loans.show', ['loan' => $otherLoan->id]))
        ->assertForbidden();
});
