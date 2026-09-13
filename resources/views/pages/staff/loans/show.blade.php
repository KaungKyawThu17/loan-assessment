<?php

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $loanId;

    public string $assigned_reviewer_id = '';

    public string $decision_notes = '';

    public function boot(): void
    {
        Gate::authorize('access-staff-area');
    }

    public function mount(LoanApplication $loan): void
    {
        Gate::authorize('view', $loan);

        $this->loanId = (int) $loan->id;
        $this->assigned_reviewer_id = (string) ($loan->assigned_reviewer_id ?? '');
    }

    public function assignReviewer(): void
    {
        Gate::authorize('access-admin-area');

        $this->resetValidation();

        $validated = $this->validate([
            'assigned_reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'loan_officer'),
            ],
        ], [
            'assigned_reviewer_id.exists' => 'Choose an existing loan officer.',
        ]);

        DB::transaction(function () use ($validated): void {
            $loan = LoanApplication::query()
                ->whereKey($this->loanId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->status !== 'pending') {
                throw ValidationException::withMessages([
                    'assigned_reviewer_id' => 'Reviewer assignment is available only while the application is pending.',
                ]);
            }

            Gate::authorize('assignReviewer', $loan);

            $loan->assigned_reviewer_id = $validated['assigned_reviewer_id'] === ''
                ? null
                : $validated['assigned_reviewer_id'];
            $loan->save();
        }, 3);

        session()->flash('success', 'Reviewer assignment updated.');
    }

    public function approve(): void
    {
        $this->recordDecision('approved');
    }

    public function reject(): void
    {
        $this->recordDecision('rejected');
    }

    private function recordDecision(string $decision): void
    {
        Gate::authorize('access-admin-area');

        $this->resetValidation();

        $validated = Validator::make([
            'decision_notes' => trim($this->decision_notes),
        ], [
            'decision_notes' => ['required', 'string', 'max:2000'],
        ], [
            'decision_notes.required' => 'Explain the approval or rejection for the customer.',
        ])->validate();

        DB::transaction(function () use ($decision, $validated): void {
            $loan = LoanApplication::query()
                ->whereKey($this->loanId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'Only pending applications can be approved or rejected. Refresh to see the current status.',
                ]);
            }

            $ability = $decision === 'approved' ? 'approve' : 'reject';
            Gate::authorize($ability, $loan);

            $loan->status = $decision;
            $loan->decision_notes = $validated['decision_notes'];
            $loan->approved_at = $decision === 'approved' ? now() : null;
            $loan->save();
        }, 3);

        $this->decision_notes = '';
        session()->flash('success', 'Loan application '.$decision.'.');
    }

    public function render(): View
    {
        $loan = LoanApplication::query()
            ->with(['customer', 'assignedReviewer'])
            ->findOrFail($this->loanId);

        Gate::authorize('view', $loan);

        $reviewers = Gate::allows('assignReviewer', $loan)
            ? User::query()->where('role', 'loan_officer')->orderBy('name')->get(['id', 'name'])
            : collect();

        return $this->view([
            'loan' => $loan,
            'reviewers' => $reviewers,
        ]);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Loan application #{{ $loan->id }}</flux:heading>
        <flux:button :href="route('staff.loans.index')" wire:navigate>Back to review list</flux:button>
    </div>

    @if (session('success'))
        <p role="status" class="rounded-lg bg-green-50 p-3 text-green-800 dark:bg-green-950 dark:text-green-200">
            {{ session('success') }}
        </p>
    @endif

    <div role="alert">
        <flux:error name="decision" />
        @if ($loan->status !== 'pending')
            <flux:error name="decision_notes" />
            <flux:error name="assigned_reviewer_id" />
        @endif
    </div>

    <x-loans.details :loan="$loan" />

    @can('assignReviewer', $loan)
        <form wire:submit="assignReviewer" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">Assign reviewer</flux:heading>

            <flux:select wire:model="assigned_reviewer_id" name="assigned_reviewer_id" label="Loan officer">
                <flux:select.option value="">Unassigned</flux:select.option>
                @foreach ($reviewers as $reviewer)
                    <flux:select.option :value="(string) $reviewer->id">{{ $reviewer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" wire:loading.attr="disabled">Save reviewer</flux:button>
        </form>
    @endcan

    @can('approve', $loan)
        <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="lg">Record a decision</flux:heading>

            <flux:textarea wire:model="decision_notes" name="decision_notes"
                label="Decision notes" description="Explain your decision. The customer can read these notes."
                rows="4" maxlength="2000" required />

            <div class="flex flex-wrap gap-3">
                <flux:button type="button" variant="primary" wire:click="approve" wire:loading.attr="disabled">
                    Approve loan
                </flux:button>

                @can('reject', $loan)
                    <flux:button type="button" variant="danger" wire:click="reject" wire:loading.attr="disabled">
                        Reject loan
                    </flux:button>
                @endcan

                <span wire:loading role="status" class="self-center text-sm">Saving…</span>
            </div>
        </section>
    @endcan
</div>
