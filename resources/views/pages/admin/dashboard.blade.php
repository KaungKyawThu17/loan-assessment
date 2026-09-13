<?php

use App\Models\LoanApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public function boot(): void
    {
        Gate::authorize('access-admin-area');
    }

    public function render(): View
    {
        $counts = LoanApplication::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS application_count')
            ->groupBy('status')
            ->pluck('application_count', 'status');

        $byStatus = [];

        foreach (LoanApplication::STATUSES as $status) {
            $byStatus[$status] = (int) ($counts[$status] ?? 0);
        }

        return $this->view([
            'summary' => [
                'total_applications' => LoanApplication::query()->count(),
                'pending_applications' => $byStatus['pending'],
                'approved_amount' => LoanApplication::query()->where('status', 'approved')->sum('amount'),
                'rejected_count' => $byStatus['rejected'],
                'average_amount' => LoanApplication::query()->avg('amount') ?? 0,
                'by_status' => $byStatus,
            ],
        ]);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Loan overview</flux:heading>
        <flux:button :href="route('staff.loans.index')" variant="primary" wire:navigate>
            Review applications
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'Total applications' => $summary['total_applications'],
            'Pending applications' => $summary['pending_applications'],
            'Approved amount' => number_format((float) $summary['approved_amount'], 2) . ' MMK',
            'Rejected applications' => $summary['rejected_count'],
            'Average loan amount' => number_format((float) $summary['average_amount'], 2) . ' MMK',
        ] as $label => $value)
            <dl class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                <dd class="mt-2 break-words text-xl font-semibold">{{ $value }}</dd>
            </dl>
        @endforeach
    </div>

    <flux:text>
        Approved amount includes applications currently approved.
        Average amount includes all applications.
    </flux:text>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Applications by status</flux:heading>

        <table class="mt-4 w-full text-left text-sm">
            <thead>
                <tr>
                    <th scope="col" class="p-3">Status</th>
                    <th scope="col" class="p-3 text-right">Applications</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['by_status'] as $status => $count)
                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="p-3">{{ ucfirst($status) }}</td>
                        <td class="p-3 text-right">{{ $count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>

