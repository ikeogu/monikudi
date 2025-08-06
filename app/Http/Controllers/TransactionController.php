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
        $search = $request->input('search');

        $transactions = Transaction::query()
        ->when($search, function ($query, $search) {
            $query->where('date', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%")
                ->orWhere('amount', 'like', "%{$search}%");
        })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Transactions/AllTransactions', [
            'transactions' => $transactions,
            'filters' => $request->only('search'),
        ]);
    }
    /**
     * Display all transactions for a specific customer.
     */
    public function index(Request $request, Customer $customer)
    {
        $search = $request->input('search');

        $transactions = Transaction::query()
            ->where('customer_id', $customer->id)
            ->when($search, function ($query, $search) {
                $query->where('date', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'customer' => $customer,
            'transactions' => $transactions,
            'filters' => $request->only('search'),
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

        $customer->transactions()->create($validated);

        return Inertia::render('Transactions/Create', [
            'customer' => $customer,
            'flash' => ['success' => 'Transaction created successfully.'],
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'date' => 'required|date',
            'customer_id' => 'required'
        ]);

        Transaction::create($validated);

        return Inertia::render('Transactions/CreateTransaction', [
            'flash' => ['success' => 'Transaction created successfully.'],
        ]);
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

        return redirect()->route('customers.transactions.index', $customer->id)->with('success', 'Transaction deleted.');
    }


    public function  quickTransaction()  {

        return Inertia::render('Transactions/CreateTransaction');

    }

    public function print(Customer $customer, Transaction $transaction)
    {
        return Inertia::render('Transactions/PrintReceipt', [
            'customer' => $customer,
            'transaction' => $transaction,
        ]);
    }
}
