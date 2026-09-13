<?php

use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $customer_id = '';

    public string $min_amount = '';

    public string $max_amount = '';

    public string $per_page = '10';

    /**
     * @var array{search: string, status: string, customer_id: string, min_amount: string, max_amount: string, per_page: string}
     */
    #[Locked]
    public array $appliedFilters = [
        'search' => '',
        'status' => '',
        'customer_id' => '',
        'min_amount' => '',
        'max_amount' => '',
        'per_page' => '10',
    ];

    public function boot(): void
    {
        Gate::authorize('access-staff-area');
    }

    public function applyFilters(): void
    {
        $this->resetValidation();

        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(LoanApplication::STATUSES)],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'min_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'max_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
            'per_page' => ['required', 'integer', Rule::in([10, 25, 50])],
        ];

        if (filled($this->min_amount)) {
            $rules['max_amount'][] = 'gte:min_amount';
        }

        $validated = $this->validate($rules, [
            'max_amount.gte' => 'Maximum amount must be greater than or equal to minimum amount.',
        ]);

        $validated['search'] = trim($validated['search']);
        $this->appliedFilters = $validated;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'customer_id', 'min_amount', 'max_amount', 'per_page', 'appliedFilters');
        $this->resetValidation();
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', LoanApplication::class);

        $user = Auth::user();
        $filters = $this->appliedFilters;
        $query = LoanApplication::query();

        if ($user->isLoanOfficer()) {
            $query->where('assigned_reviewer_id', $user->id);
        }

        if ($filters['search'] !== '') {
            $pattern = '%'.$filters['search'].'%';

            $query->where(function (Builder $search) use ($pattern): void {
                $search->where('purpose', 'like', $pattern)
                    ->orWhereHas('customer', function (Builder $customer) use ($pattern): void {
                        $customer->where(function (Builder $contact) use ($pattern): void {
                            $contact->where('name', 'like', $pattern)
                                ->orWhere('email', 'like', $pattern)
                                ->orWhere('phone', 'like', $pattern);
                        });
                    });
            });
        }

        if (filled($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (filled($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (filled($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        return $this->view([
            'loans' => $query
                ->with(['customer', 'assignedReviewer'])
                ->orderByDesc('id')
                ->paginate((int) $filters['per_page']),
        ]);
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Loan review</flux:heading>
        <flux:text>
            @if (auth()->user()->isLoanOfficer())
                Applications assigned to you.
            @else
                Search applications, assign reviewers, and open a loan to record a decision.
            @endif
        </flux:text>
    </div>

    <form wire:submit="applyFilters" class="grid gap-4 rounded-xl border border-zinc-200 p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700">
        <flux:input wire:model="search" name="search" label="Search"
            placeholder="Customer name, email, phone, or purpose" maxlength="100" />

        <flux:select wire:model="status" name="status" label="Status">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (\App\Models\LoanApplication::STATUSES as $option)
                <flux:select.option :value="$option">{{ ucfirst($option) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="customer_id" name="customer_id" label="Customer ID"
            type="number" min="1" step="1" placeholder="Any customer" />

        <flux:input wire:model="min_amount" name="min_amount" label="Minimum amount (MMK)"
            type="number" min="0" max="10000000" step="0.01" />

        <flux:input wire:model="max_amount" name="max_amount" label="Maximum amount (MMK)"
            type="number" min="0" max="10000000" step="0.01" />

        <flux:select wire:model="per_page" name="per_page" label="Applications per page">
            <flux:select.option value="10">10</flux:select.option>
            <flux:select.option value="25">25</flux:select.option>
            <flux:select.option value="50">50</flux:select.option>
        </flux:select>

        <div class="flex flex-wrap gap-3 sm:col-span-2 lg:col-span-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                Apply filters
            </flux:button>
            <flux:button type="button" wire:click="resetFilters" wire:loading.attr="disabled">
                Reset
            </flux:button>
            <span wire:loading role="status" class="self-center text-sm">Updating list…</span>
        </div>
    </form>

    <flux:text>{{ $loans->total() }} matching applications</flux:text>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Loan applications available to you</caption>
            <thead>
                <tr>
                    <th scope="col" class="p-3">Application</th>
                    <th scope="col" class="p-3">Customer</th>
                    <th scope="col" class="p-3">Amount (MMK)</th>
                    <th scope="col" class="p-3">Status</th>
                    <th scope="col" class="p-3">Reviewer</th>
                    <th scope="col" class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr wire:key="staff-loan-{{ $loan->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="p-3">
                            <div>#{{ $loan->id }}</div>
                            <div>{{ $loan->purpose }}</div>
                        </td>
                        <td class="p-3">
                            <div>{{ $loan->customer->name }}</div>
                            <div class="text-xs text-zinc-500">Customer #{{ $loan->customer_id }}</div>
                        </td>
                        <td class="whitespace-nowrap p-3">{{ number_format((float) $loan->amount, 2) }}</td>
                        <td class="p-3">{{ ucfirst($loan->status) }}</td>
                        <td class="p-3">{{ $loan->assignedReviewer?->name ?? 'Unassigned' }}</td>
                        <td class="p-3">
                            <flux:button size="sm" :href="route('staff.loans.show', ['loan' => $loan->id])" wire:navigate>
                                Open
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-zinc-500">
                            No applications match these filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $loans->links() }}
</div>
