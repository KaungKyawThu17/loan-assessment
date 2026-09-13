<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isLoanOfficer()) {
            return $customer->loanApplications()
                ->where('assigned_reviewer_id', $user->id)
                ->exists();
        }

        return $user->isCustomer()
             && (int) $customer->user_id === (int) $user->id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isCustomer()
             && (int) $customer->user_id === (int) $user->id;
    }
}
