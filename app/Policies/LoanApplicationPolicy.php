<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;

class LoanApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLoanOfficer() || $user->isCustomer();
    }

    public function view(User $user, LoanApplication $loanApplication): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isLoanOfficer()) {
            return (int) $loanApplication->assigned_reviewer_id
                === (int) $user->id;
        }

        return $user->isCustomer()
             && $user->customer()
                 ->whereKey($loanApplication->customer_id)
                 ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isCustomer()
            && $user->customer()->exists();
    }

    public function update(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isCustomer()
             && $this->view($user, $loanApplication)
             && $loanApplication->status === 'pending';
    }

    public function cancel(User $user, LoanApplication $loanApplication): bool
    {
        return $this->update($user, $loanApplication);
    }

    public function approve(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin()
             && $loanApplication->status === 'pending';
    }

    public function reject(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin()
             && $loanApplication->status === 'pending';
    }

    public function assignReviewer(User $user, LoanApplication $loanApplication): bool
    {
        return $user->isAdmin()
             && $loanApplication->status === 'pending';
    }
}
