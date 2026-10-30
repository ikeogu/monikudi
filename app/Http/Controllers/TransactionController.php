<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Deposits and withdrawals, newest first. Collectors only see their own collections.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'type' => ['nullable', Rule::in([Transaction::TYPE_DEPOSIT, Transaction::TYPE_WITHDRAWAL])],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'collector' => 'nullable|integer',
        ]);

        $query = Transaction::query()
            ->visibleTo($request->user())
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('customer', fn ($query) => $query->search($search)))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('date', '<=', $to))
            ->when($filters['collector'] ?? null, fn ($query, $collector) => $query->where('collected_by', $collector));

        return Inertia::render('Transactions/Index', [
            'transactions' => (clone $query)
                ->with(['customer:id,reg_no,first_name,last_name,phone', 'collector:id,name'])
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'totals' => [
                'deposits' => (float) (clone $query)->deposits()->sum('amount'),
                'withdrawals' => (float) (clone $query)->withdrawals()->sum('amount'),
            ],
            'filters' => $filters,
            'collectors' => $request->user()->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : [],
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    /**
     * New deposit or withdrawal form.
     */
    public function create(Request $request)
    {
        return Inertia::render('Transactions/Create', [
            'type' => $request->input('type') === Transaction::TYPE_WITHDRAWAL ? Transaction::TYPE_WITHDRAWAL : Transaction::TYPE_DEPOSIT,
            'customer' => $request->filled('customer')
                ? Customer::find($request->input('customer'), ['id', 'reg_no', 'first_name', 'last_name', 'phone', 'wallet_balance'])
                : null,
            'collectors' => $this->collectorOptions($request->user()),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Post a deposit or withdrawal and update the customer's balance.
     */
    public function store(Request $request, Ledger $ledger)
    {
        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->withoutTrashed()],
            ...$this->rules($request->user()),
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $transaction = $ledger->record($customer, collect($validated)->except('customer_id')->all(), $request->user());

        $label = $transaction->isDeposit() ? 'Deposit' : 'Withdrawal';

        return redirect()->route('transactions.show', $transaction)
            ->with('success', "{$label} of ₦".number_format($transaction->amount, 2)." saved for {$customer->full_name}.");
    }

    /**
     * Receipt for a single entry.
     */
    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load(['customer', 'collector:id,name', 'creator:id,name']),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    public function edit(Request $request, Transaction $transaction)
    {
        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction->load('customer'),
            'collectors' => $this->collectorOptions($request->user()),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'today' => today()->toDateString(),
        ]);
    }

    public function update(Request $request, Transaction $transaction, Ledger $ledger)
    {
        $ledger->update($transaction, $request->validate($this->rules($request->user())));

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaction updated.');
    }

    public function destroy(Transaction $transaction, Ledger $ledger)
    {
        $ledger->delete($transaction);

        return redirect()->route('customers.show', $transaction->customer_id)->with('success', 'Transaction deleted and balance adjusted.');
    }

    private function rules(User $user): array
    {
        return [
            'type' => ['required', Rule::in([Transaction::TYPE_DEPOSIT, Transaction::TYPE_WITHDRAWAL])],
            'amount' => 'required|numeric|min:1|max:100000000',
            'date' => 'required|date|before_or_equal:today',
            'payment_method' => ['required', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
            // Collectors can only record money they collected themselves.
            'collected_by' => $user->isAdmin()
                ? ['required', Rule::exists('users', 'id')]
                : ['required', Rule::in([$user->id])],
            'description' => 'nullable|string|max:255',
        ];
    }

    private function collectorOptions(User $user)
    {
        return $user->isAdmin()
            ? User::active()->orderBy('name')->get(['id', 'name', 'role'])
            : collect([$user->only('id', 'name', 'role')]);
    }
}
