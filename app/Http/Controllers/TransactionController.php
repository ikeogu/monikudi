<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SavingsPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
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
        [$filters, $query] = $this->filtered($request);

        return Inertia::render('Transactions/Index', [
            'transactions' => (clone $query)
                ->with(['customer:id,reg_no,first_name,last_name,phone', 'plan:id,name', 'collector:id,name'])
                ->inLedgerOrder(descending: true)
                ->paginate(20)
                ->withQueryString(),
            'totals' => [
                'deposits' => (float) (clone $query)->deposits()->sum('amount'),
                'withdrawals' => (float) (clone $query)->withdrawals()->sum('amount'),
                'commission' => (float) (clone $query)->commissions()->sum('amount'),
            ],
            'filters' => $filters,
            'collectors' => $request->user()->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : [],
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    /**
     * The filtered list as an Excel file, newest first.
     */
    public function export(Request $request)
    {
        [$filters, $query] = $this->filtered($request);
        $name = match ($filters['type'] ?? null) {
            Transaction::TYPE_DEPOSIT => 'Deposits',
            Transaction::TYPE_WITHDRAWAL => 'Withdrawals',
            Transaction::TYPE_COMMISSION => 'Commission',
            default => 'Transactions',
        };

        $rows = $query->with(['customer', 'plan:id,name', 'collector:id,name'])
            ->inLedgerOrder(descending: true)
            ->lazy(500)
            ->map->toExportRow();

        return Spreadsheet::make()
            ->sheet($name, Transaction::EXPORT_HEADINGS, $rows)
            ->download(strtolower($name).'-'.today()->toDateString().'.xlsx');
    }

    /**
     * New deposit or withdrawal form.
     */
    public function create(Request $request)
    {
        $customer = $request->filled('customer')
            ? Customer::find($request->input('customer'), ['id', 'reg_no', 'first_name', 'last_name', 'phone', 'wallet_balance'])
            : null;

        return Inertia::render('Transactions/Create', [
            'type' => $request->input('type') === Transaction::TYPE_WITHDRAWAL ? Transaction::TYPE_WITHDRAWAL : Transaction::TYPE_DEPOSIT,
            'customer' => $customer,
            'plans' => $customer ? $customer->plans()->active()->oldest()->get()->map->toFormArray() : [],
            'plan' => $request->input('plan'),
            'collectors' => $this->collectorOptions($request->user()),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Post a deposit or withdrawal on one of a customer's plans.
     */
    public function store(Request $request, Ledger $ledger)
    {
        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->withoutTrashed()],
            'savings_plan_id' => [
                'required',
                Rule::exists('savings_plans', 'id')->where('customer_id', $request->input('customer_id')),
            ],
            'close_plan' => 'boolean',
            ...$this->rules($request->user()),
        ]);

        $plan = SavingsPlan::with('customer')->findOrFail($validated['savings_plan_id']);
        $transaction = $ledger->record(
            $plan,
            collect($validated)->except(['customer_id', 'savings_plan_id', 'close_plan'])->all(),
            $request->user(),
            $request->boolean('close_plan'),
        );

        $message = "{$transaction->typeLabel()} of ₦".number_format($transaction->amount, 2)." saved for {$plan->customer->full_name}.";
        $commission = (float) $transaction->pageCommissions()->sum('amount');
        if ($commission > 0) {
            $message .= ' ₦'.number_format($commission, 2).' of it is commission for a new page.';
        }

        return redirect()->route('transactions.show', $transaction)->with('success', $message);
    }

    /**
     * Receipt for a single entry.
     */
    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load([
                'customer', 'plan:id,name,daily_amount,status', 'collector:id,name', 'creator:id,name',
                'pageCommissions' => fn ($query) => $query->inLedgerOrder(),
                'source:id,date,amount,page_number',
            ]),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    public function edit(Request $request, Transaction $transaction)
    {
        if ($transaction->isCommission()) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'Commission is worked out from the deposits. Edit or delete the deposit instead.');
        }

        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction->load(['customer', 'plan:id,name']),
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

    /**
     * The validated list filters and a query for the matching entries.
     *
     * @return array{0: array, 1: Builder}
     */
    private function filtered(Request $request): array
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'type' => ['nullable', Rule::in(array_keys(Transaction::TYPE_LABELS))],
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

        return [$filters, $query];
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
