<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function (): RedirectResponse {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isLoanOfficer()) {
            return redirect()->route('staff.loans.index');
        }

        if ($user->isCustomer()) {
            return redirect()->route('customer.loans.index');
        }

        abort(403);
    })->name('dashboard');

    Route::middleware('can:access-customer-area')->prefix('customer')->name('customer.')->group(function (): void {
        Route::livewire('/loans', 'pages::customer.loans.index')->name('loans.index');
        Route::livewire('/loans/create', 'pages::customer.loans.create')->name('loans.create');
        Route::livewire('/loans/{loan}', 'pages::customer.loans.show')->whereNumber('loan')->name('loans.show');
    });

    Route::middleware('can:access-staff-area')->prefix('staff')->name('staff.')->group(function (): void {
        Route::livewire('/loans', 'pages::staff.loans.index')->name('loans.index');
        Route::livewire('/loans/{loan}', 'pages::staff.loans.show')->whereNumber('loan')->name('loans.show');
    });

    Route::middleware('can:access-admin-area')->prefix('admin')->name('admin.')->group(function (): void {
        Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
