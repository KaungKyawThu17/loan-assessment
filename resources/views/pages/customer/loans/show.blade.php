<?php

use App\Models\LoanApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $loanId;

    public function boot(): void
    {
        Gate::authorize('access-customer-area');
    }

    public function mount(LoanApplication $loan): void
    {
        Gate::authorize('view', $loan);
        $this->loanId = (int) $loan->id;
    }

    public function render(): View
    {
        $loan = LoanApplication::query()
            ->with(['customer', 'assignedReviewer'])
            ->findOrFail($this->loanId);

        Gate::authorize('view', $loan);

        return $this->view(['loan' => $loan]);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Loan application #{{ $loan->id }}</flux:heading>
        <flux:button :href="route('customer.loans.index')" wire:navigate>Back to my loans</flux:button>
    </div>

    @if (session('success'))
        <p role="status" class="rounded-lg bg-green-50 p-3 text-green-800 dark:bg-green-950 dark:text-green-200">
            {{ session('success') }}
        </p>
    @endif

    <x-loans.details :loan="$loan" />
</div>
