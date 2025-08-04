<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Display all transactions for a specific customer.
     */
    public function allTransactionz(Request $request)
    {
        $transactions = Transaction::latest()->get();

        return Inertia::render('Transactions/AllTransactions', [
            'transactions' => $transactions
        ]);
    }
    /**
     * Display all transactions for a specific customer.
     */
    public function index(Customer $customer)
    {
        $transactions = $customer->transactions()->latest()->get();

        return Inertia::render('Transactions/Index', [
            'customer' => $customer,
            'transactions' => $transactions
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(Customer $customer)
    {
        return Inertia::render('Transactions/Create', [
            'customer' => $customer
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        $validated['customer_id'] = $customer->id;

        Transaction::create($validated);

        return redirect()->route('transactions.index', $customer->id)->with('success', 'Transaction recorded successfully.');
    }

    /**
     * Show the form for editing a transaction.
     */
    public function edit(Customer $customer, Transaction $transaction)
    {
        return Inertia::render('Transactions/Edit', [
            'customer' => $customer,
            'transaction' => $transaction
        ]);
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, Customer $customer, Transaction $transaction)
    {
        $validated = $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        $transaction->update($validated);

        return redirect()->route('transactions.index', $customer->id)->with('success', 'Transaction updated.');
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Customer $customer, Transaction $transaction)
    {
        $transaction->delete();

        return redirect()->route('transactions.index', $customer->id)->with('success', 'Transaction deleted.');
    }
}
