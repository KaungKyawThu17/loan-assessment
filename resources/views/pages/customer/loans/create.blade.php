<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $amount = '';

    public string $term_months = '12';

    public string $purpose = '';

    public string $supporting_notes = '';

    public function boot(): void
    {
        Gate::authorize('access-customer-area');
        Gate::authorize('create', LoanApplication::class);
    }

    public function submit(): void
    {
        Gate::authorize('create', LoanApplication::class);

        $this->resetValidation();

        $validated = $this->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:100000', 'max:10000000'],
            'term_months' => ['required', 'integer', 'between:3,24'],
            'purpose' => ['required', 'string', 'max:255'],
            'supporting_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.min' => 'The minimum loan amount is 100,000 MMK.',
            'amount.max' => 'The maximum loan amount is 10,000,000 MMK.',
            'amount.decimal' => 'Use no more than two decimal places.',
            'term_months.integer' => 'The term must be a whole number of months.',
            'term_months.between' => 'Choose a term between 3 and 24 months.',
        ]);

        $loan = DB::transaction(function () use ($validated): LoanApplication {
            $customer = Customer::query()
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->firstOrFail();

            $pendingLoan = $customer->loanApplications()
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first(['id']);

            if ($pendingLoan !== null) {
                throw ValidationException::withMessages([
                    'application' => 'You already have a pending loan application.',
                ]);
            }

            $loan = new LoanApplication;
            $loan->amount = $validated['amount'];
            $loan->term_months = (int) $validated['term_months'];
            $loan->purpose = trim($validated['purpose']);

            $notes = trim($validated['supporting_notes'] ?? '');
            $loan->supporting_notes = $notes === '' ? null : $notes;

            $loan->interest_rate = config('loans.annual_interest_rate');
            $loan->status = 'pending';
            $loan->application_date = now()->toDateString();
            $loan->approved_at = null;
            $loan->assigned_reviewer_id = null;
            $loan->decision_notes = null;

            $customer->loanApplications()->save($loan);

            return $loan;
        }, 3);

        session()->flash('success', 'Loan application created successfully.');

        $this->redirectRoute(
            'customer.loans.show',
            ['loan' => $loan->id],
            navigate: true,
        );
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Apply for a loan</flux:heading>
        <flux:text>Choose an amount and term, then explain the purpose of your loan.</flux:text>
        <flux:text>Annual flat interest rate: {{ config('loans.annual_interest_rate') }}%.</flux:text>
    </div>

    <form wire:submit="submit" class="space-y-5">
        <flux:input
            wire:model="amount"
            name="amount"
            label="Loan amount (MMK)"
            type="number"
            min="100000"
            max="10000000"
            step="0.01"
            description="Enter 100,000–10,000,000 MMK without commas."
            required
        />

        <flux:input
            wire:model="term_months"
            name="term_months"
            label="Term in months"
            type="number"
            min="3"
            max="24"
            step="1"
            required
        />

        <flux:input
            wire:model="purpose"
            name="purpose"
            label="Purpose"
            maxlength="255"
            required
        />

        <flux:textarea
            wire:model="supporting_notes"
            name="supporting_notes"
            label="Supporting notes (optional)"
            maxlength="2000"
            rows="4"
        />

        <div role="alert">
            <flux:error name="application" />
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Submit application</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </flux:button>

            <flux:button :href="route('customer.loans.index')" wire:navigate>
                Back to my applications
            </flux:button>
        </div>
    </form>
</div>
