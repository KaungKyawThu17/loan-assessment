<?php

use App\Services\RepaymentCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function boot(): void
    {
        Gate::authorize('access-customer-area');
    }

    public function render(): View
    {
        $customer = Auth::user()->customer()->firstOrFail();
        $applications = $customer->loanApplications();

        return $this->view([
            'loans' => (clone $applications)->orderByDesc('id')->paginate(10),
            'pendingCount' => (clone $applications)->where('status', 'pending')->count(),
            'approvedAmount' => (clone $applications)->where('status', 'approved')->sum('amount'),
            'calculator' => app(RepaymentCalculator::class),
        ]);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">My loans</flux:heading>
        <flux:button :href="route('customer.loans.create')" variant="primary" wire:navigate>
            Apply for a loan
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <dl class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <dt class="text-sm text-zinc-500">My applications</dt>
            <dd class="mt-2 text-xl font-semibold">{{ $loans->total() }}</dd>
        </dl>
        <dl class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <dt class="text-sm text-zinc-500">Pending applications</dt>
            <dd class="mt-2 text-xl font-semibold">{{ $pendingCount }}</dd>
        </dl>
        <dl class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <dt class="text-sm text-zinc-500">Currently approved amount</dt>
            <dd class="mt-2 text-xl font-semibold">{{ number_format((float) $approvedAmount, 2) }} MMK</dd>
        </dl>
    </div>

    <flux:text>
        Repayment figures are calculated from your requested terms.
        Before approval they are estimates. Open an application for its dates and full schedule.
    </flux:text>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Your loan history and repayment summaries</caption>
            <thead>
                <tr>
                    <th scope="col" class="p-3">Application</th>
                    <th scope="col" class="p-3">Amount (MMK)</th>
                    <th scope="col" class="p-3">Status</th>
                    <th scope="col" class="p-3">Applied</th>
                    <th scope="col" class="p-3">Repayment summary (MMK)</th>
                    <th scope="col" class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loans as $loan)
                    @php
                        $repayment = $calculator->calculate(
                            $loan->amount,
                            $loan->interest_rate,
                            $loan->term_months,
                            ($loan->approved_at ?? $loan->application_date)->toDateString(),
                        );
                    @endphp

                    <tr wire:key="customer-loan-{{ $loan->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="p-3">
                            <div>#{{ $loan->id }}</div>
                            <div>{{ $loan->purpose }}</div>
                        </td>
                        <td class="whitespace-nowrap p-3">{{ $calculator->format($repayment['principal_minor']) }}</td>
                        <td class="p-3">{{ ucfirst($loan->status) }}</td>
                        <td class="whitespace-nowrap p-3">{{ $loan->application_date->format('Y-m-d') }}</td>
                        <td class="whitespace-nowrap p-3">
                            <div>Monthly: {{ $calculator->format($repayment['regular_instalment_minor']) }}</div>
                            <div>Total: {{ $calculator->format($repayment['total_minor']) }}</div>
                            <div class="text-xs text-zinc-500">Final instalment may differ slightly.</div>
                        </td>
                        <td class="p-3">
                            <flux:button size="sm" :href="route('customer.loans.show', ['loan' => $loan->id])" wire:navigate>
                                View
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-zinc-500">
                            You have no loan applications yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $loans->links() }}
</div>
