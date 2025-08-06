<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->except(['search', 'show']);
    Route::resource('customers.transactions', TransactionController::class)->except(['quickTransaction', 'allTransactionz']);
    Route::get('quickTransaction', [TransactionController::class, 'quickTransaction'])->name('quickTransaction');
    Route::post('addTransaction', [TransactionController::class, 'storeTransaction'])->name('transactions.store');
    Route::get('/transactions', [TransactionController::class, 'allTransactionz'])->name('allTransactionz');
    Route::get('/customers/{customer}/transactions/{transaction}/print', [TransactionController::class, 'print'])->name('transactions.print');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
});

require __DIR__.'/auth.php';