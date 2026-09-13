<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

test('admin can approve through the page', function () {
    $this->freezeTime();
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create()->user;
    $loan = LoanApplication::factory()->for($customer->customer)->create();

    Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('decision_notes', 'Income and requested terms reviewed.')
        ->call('approve')
        ->assertHasNoErrors();

    $loan->refresh();
    $this->assertSame('approved', $loan->status);
    $this->assertSame(now()->toDateTimeString(), $loan->approved_at->toDateTimeString());
    $this->assertSame('Income and requested terms reviewed.', $loan->decision_notes);

    $this->actingAs($customer)
        ->get(route('customer.loans.show', ['loan' => $loan->id]))
        ->assertOk()
        ->assertSee('Income and requested terms reviewed.')
        ->assertSee('91,666.63');
});

test('rejection requires notes and preserves input on error', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $loan = LoanApplication::factory()->create();

    $page = Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('decision_notes', '   ')
        ->call('reject')
        ->assertHasErrors(['decision_notes' => 'required'])
        ->assertSee('Explain the approval or rejection for the customer.');

    $this->assertSame('pending', $loan->fresh()->status);

    $page->set('decision_notes', 'Insufficient supporting information.')
        ->call('reject')
        ->assertHasNoErrors();

    $loan->refresh();
    $this->assertSame('rejected', $loan->status);
    $this->assertNull($loan->approved_at);
    $this->assertSame('Insufficient supporting information.', $loan->decision_notes);
});

test('non admins cannot call the review action', function (string $role, string $decision) {
    $admin = User::factory()->create(['role' => 'admin']);
    $actor = $role === 'customer'
        ? Customer::factory()->create()->user
        : User::factory()->create(['role' => 'loan_officer']);
    $owner = $role === 'customer' ? $actor : Customer::factory()->create()->user;
    $loan = LoanApplication::factory()->for($owner->customer)->create(['assigned_reviewer_id' => $role === 'loan_officer' ? $actor->id : null]);

    $page = Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('decision_notes', 'Attempt by a non-admin.');

    $this->actingAs($actor);
    $page->call($decision === 'approved' ? 'approve' : 'reject')
        ->assertForbidden();

    $this->assertDatabaseHas('loan_applications', [
        'id' => $loan->id,
        'status' => 'pending',
        'decision_notes' => null,
        'approved_at' => null,
    ]);
})->with([
    ['customer', 'approved'],
    ['customer', 'rejected'],
    ['loan_officer', 'approved'],
    ['loan_officer', 'rejected'],
]);

test('existing decisions and other states cannot be overwritten', function (string $status, string $decision) {
    $admin = User::factory()->create(['role' => 'admin']);
    $loan = LoanApplication::factory()->create([
        'status' => $status,
        'approved_at' => in_array($status, ['approved', 'disbursed', 'closed'], true) ? now() : null,
        'decision_notes' => 'Original decision.',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('decision_notes', 'Attempted replacement.')
        ->call($decision === 'approved' ? 'approve' : 'reject')
        ->assertHasErrors('decision')
        ->assertSee('Only pending applications can be approved or rejected. Refresh to see the current status.');

    $this->assertSame($status, $loan->fresh()->status);
    $this->assertSame('Original decision.', $loan->fresh()->decision_notes);
})->with([
    ['approved', 'approved'],
    ['approved', 'rejected'],
    ['rejected', 'approved'],
    ['rejected', 'rejected'],
    ['cancelled', 'approved'],
    ['disbursed', 'rejected'],
    ['closed', 'approved'],
]);

test('admin can assign and clear a reviewer', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'loan_officer']);
    $loan = LoanApplication::factory()->create();

    $page = Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('assigned_reviewer_id', (string) $officer->id)
        ->call('assignReviewer')
        ->assertHasNoErrors();

    $this->assertSame($officer->id, (int) $loan->fresh()->assigned_reviewer_id);

    $page->set('assigned_reviewer_id', '')
        ->call('assignReviewer')
        ->assertHasNoErrors();

    $this->assertNull($loan->fresh()->assigned_reviewer_id);
});

test('a customer cannot be selected as reviewer', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create()->user;
    $loan = LoanApplication::factory()->for($customer->customer)->create();

    Livewire::actingAs($admin)
        ->test('pages::staff.loans.show', ['loan' => $loan])
        ->set('assigned_reviewer_id', (string) $customer->id)
        ->call('assignReviewer')
        ->assertHasErrors(['assigned_reviewer_id' => 'exists'])
        ->assertSee('Choose an existing loan officer.');

    $this->assertNull($loan->fresh()->assigned_reviewer_id);
});

test('officer can view only assigned loans and cannot invoke hidden actions', function () {
    $officer = User::factory()->create(['role' => 'loan_officer']);
    $assigned = LoanApplication::factory()->create(['assigned_reviewer_id' => $officer->id]);
    $other = LoanApplication::factory()->create();

    $this->actingAs($officer)
        ->get(route('staff.loans.show', ['loan' => $assigned->id]))
        ->assertOk()
        ->assertDontSee('Approve loan');

    $this->get(route('staff.loans.show', ['loan' => $other->id]))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertForbidden();

    Livewire::actingAs($officer)
        ->test('pages::staff.loans.show', ['loan' => $assigned])
        ->set('decision_notes', 'Calling the hidden action directly.')
        ->call('approve')
        ->assertForbidden();

    $this->assertSame('pending', $assigned->fresh()->status);

    Livewire::actingAs($officer)
        ->test('pages::staff.loans.show', ['loan' => $assigned])
        ->set('assigned_reviewer_id', '')
        ->call('assignReviewer')
        ->assertForbidden();

    $this->assertSame($officer->id, $assigned->fresh()->assigned_reviewer_id);
});

test('search and customer filters cannot escape officer scope', function () {
    $officer = User::factory()->create(['role' => 'loan_officer']);
    $owner = Customer::factory()->create()->user;
    $hiddenOwner = Customer::factory()->create()->user;
    $visible = LoanApplication::factory()->for($owner->customer)->create([
        'purpose' => 'shared search phrase',
        'assigned_reviewer_id' => $officer->id,
    ]);
    $hidden = LoanApplication::factory()->for($hiddenOwner->customer)->create(['purpose' => 'shared search phrase']);

    $page = Livewire::actingAs($officer)
        ->test('pages::staff.loans.index')
        ->set('search', 'shared search phrase')
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->modelKeys() === [$visible->id]);

    $page->set('search', $hiddenOwner->customer->name)
        ->set('customer_id', (string) $hidden->customer_id)
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->total() === 0);

    $page->call('resetFilters')
        ->assertHasNoErrors()
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->modelKeys() === [$visible->id]);
});

test('admin filters combine and invalid ranges show errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create()->user;
    $expected = LoanApplication::factory()->for($customer->customer)->create(['amount' => '200000.00']);
    LoanApplication::factory()->for($customer->customer)->create(['status' => 'rejected', 'amount' => '300000.00']);
    LoanApplication::factory()->create(['amount' => '200000.00']);

    $page = Livewire::actingAs($admin)
        ->test('pages::staff.loans.index')
        ->set('status', 'pending')
        ->set('customer_id', (string) $expected->customer_id)
        ->set('min_amount', '100000')
        ->set('max_amount', '250000')
        ->set('per_page', '25')
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->modelKeys() === [$expected->id] && $loans->perPage() === 25);

    $page->set('min_amount', '500000')
        ->set('max_amount', '100000')
        ->call('applyFilters')
        ->assertHasErrors(['max_amount' => 'gte'])
        ->assertSee('Maximum amount must be greater than or equal to minimum amount.')
        ->assertSet('min_amount', '500000')
        ->assertSet('max_amount', '100000')
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->modelKeys() === [$expected->id]);

    $page->set('min_amount', '100000')
        ->set('max_amount', '250000')
        ->set('per_page', '1000')
        ->call('applyFilters')
        ->assertHasErrors(['per_page' => 'in'])
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->perPage() === 25);

    $page->call('gotoPage', 2)
        ->call('resetFilters')
        ->assertHasNoErrors()
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSet('customer_id', '')
        ->assertSet('min_amount', '')
        ->assertSet('max_amount', '')
        ->assertSet('per_page', '10')
        ->assertViewHas('loans', fn (LengthAwarePaginator $loans): bool => $loans->total() === 3 && $loans->perPage() === 10 && $loans->currentPage() === 1);
});

test('dashboard totals use the documented definitions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = Customer::factory()->create()->user;

    LoanApplication::factory()->for($customer->customer)->create(['amount' => '1000000.00', 'status' => 'approved', 'approved_at' => now()]);
    LoanApplication::factory()->for($customer->customer)->create(['amount' => '200000.00']);
    LoanApplication::factory()->for($customer->customer)->create(['amount' => '300000.00', 'status' => 'rejected']);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertViewHas('summary', function (array $summary): bool {
            $this->assertSame(3, $summary['total_applications']);
            $this->assertSame(1, $summary['pending_applications']);
            $this->assertSame(1, $summary['rejected_count']);
            $this->assertSame('1000000.00', number_format((float) $summary['approved_amount'], 2, '.', ''));
            $this->assertSame('500000.00', number_format((float) $summary['average_amount'], 2, '.', ''));
            $this->assertSame(1, $summary['by_status']['approved']);
            $this->assertSame(0, $summary['by_status']['closed']);

            return true;
        });

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('staff.loans.index'))->assertForbidden();
});
