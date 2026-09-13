@props(['loan'])

@php
    $calculator = app(\App\Services\RepaymentCalculator::class);
    $startDate = ($loan->approved_at ?? $loan->application_date)->toDateString();
    $repayment = $calculator->calculate(
        $loan->amount,
        $loan->interest_rate,
        $loan->term_months,
        $startDate,
    );
@endphp

<div class="space-y-6">
    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Customer information</flux:heading>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-zinc-500">Name</dt>
                <dd>{{ $loan->customer->name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Email</dt>
                <dd class="break-all">{{ $loan->customer->email }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Phone</dt>
                <dd>{{ $loan->customer->phone }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Address</dt>
                <dd class="whitespace-pre-wrap">{{ $loan->customer->address }}</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Loan details</flux:heading>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-sm text-zinc-500">Amount</dt>
                <dd>{{ $calculator->format($repayment['principal_minor']) }} MMK</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Term</dt>
                <dd>{{ $loan->term_months }} months</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Annual flat interest rate</dt>
                <dd>{{ $loan->interest_rate }}%</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Status</dt>
                <dd>{{ ucfirst($loan->status) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Application date</dt>
                <dd>{{ $loan->application_date->format('Y-m-d') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Approved at</dt>
                <dd>{{ $loan->approved_at?->format('Y-m-d H:i') ?? 'Not approved' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Reviewer</dt>
                <dd>{{ $loan->assignedReviewer?->name ?? 'Unassigned' }}</dd>
            </div>
        </dl>

        <div class="mt-5 space-y-4">
            <div>
                <flux:heading>Purpose</flux:heading>
                <p class="whitespace-pre-wrap">{{ $loan->purpose }}</p>
            </div>
            <div>
                <flux:heading>Supporting notes</flux:heading>
                <p class="whitespace-pre-wrap">{{ $loan->supporting_notes ?: 'No supporting notes.' }}</p>
            </div>
            <div>
                <flux:heading>Decision notes</flux:heading>
                <p class="whitespace-pre-wrap">{{ $loan->decision_notes ?: 'No decision has been recorded.' }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">Repayment summary</flux:heading>

        @if ($loan->approved_at !== null)
            <flux:text>Scheduled dates start one month after approval.</flux:text>
        @else
            <flux:text>
                This is an estimate based on the application date.
                It does not mean the loan is approved or payment is due.
            </flux:text>
        @endif

        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-zinc-500">Total interest</dt>
                <dd>{{ $calculator->format($repayment['interest_minor']) }} MMK</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Total payable</dt>
                <dd>{{ $calculator->format($repayment['total_minor']) }} MMK</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Regular monthly instalment</dt>
                <dd>{{ $calculator->format($repayment['regular_instalment_minor']) }} MMK</dd>
            </div>
        </dl>

        <div class="mt-5 max-h-80 overflow-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Calculated repayment schedule</caption>
                <thead class="sticky top-0 bg-white dark:bg-zinc-900">
                    <tr>
                        <th scope="col" class="p-3">Instalment</th>
                        <th scope="col" class="p-3">Date</th>
                        <th scope="col" class="p-3 text-right">Amount (MMK)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($repayment['instalments'] as $instalment)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $instalment['number'] }}</td>
                            <td class="whitespace-nowrap p-3">{{ $instalment['due_date'] }}</td>
                            <td class="whitespace-nowrap p-3 text-right">
                                {{ $calculator->format($instalment['amount_minor']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <flux:text class="mt-3">
            The final instalment includes the rounding adjustment.
            This schedule shows planned amounts; payment collection is not tracked.
        </flux:text>
    </section>
</div>
