<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Ledger;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Display a list of customers.
     */
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->search($request->input('search'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for registering a new customer.
     */
    public function create()
    {
        return Inertia::render('Customers/Create', [
            'suggestedRegNo' => Customer::nextRegNo(),
        ]);
    }

    /**
     * Store a newly registered customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['reg_no'] = strtoupper($validated['reg_no'] ?? '') ?: Customer::nextRegNo();
        $validated['created_by'] = $request->user()->id;

        $customer = Customer::create($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', "{$customer->full_name} registered as {$customer->reg_no}.");
    }

    /**
     * Customer statement: totals plus every entry with its running balance.
     */
    public function show(Request $request, Customer $customer)
    {
        $statement = $this->statement($request, $customer);
        $history = $statement['history'];

        return Inertia::render('Customers/Show', [
            'customer' => $customer->load('registeredBy:id,name'),
            'summary' => [
                'deposits' => (float) $history->where('type', Transaction::TYPE_DEPOSIT)->sum('amount'),
                'withdrawals' => (float) $history->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount'),
                'balance' => $customer->balance,
                'opening' => $statement['opening'],
                'historyBalance' => $statement['closing'],
            ],
            'transactions' => array_reverse($statement['entries']),
            'filters' => $statement['filters'],
        ]);
    }

    /**
     * The statement as an Excel sheet, oldest entry first.
     */
    public function export(Request $request, Customer $customer)
    {
        $statement = $this->statement($request, $customer);
        $filters = $statement['filters'];

        $rows = array_map(fn (Transaction $t) => [
            $t->date->toDateString(),
            $t->created_at?->format('H:i'),
            $t->receipt_no,
            $t->isDeposit() ? 'Deposit' : 'Withdrawal',
            Transaction::PAYMENT_METHODS[$t->payment_method] ?? $t->payment_method,
            $t->collector?->name,
            $t->description,
            $t->isDeposit() ? $t->amount : null,
            $t->isDeposit() ? null : $t->amount,
            $t->running_balance,
        ], $statement['entries']);

        if (! empty($filters['from'])) {
            array_unshift($rows, [$filters['from'], null, null, 'Opening balance', null, null, null, null, null, $statement['opening']]);
        }

        $dates = empty($filters['from']) && empty($filters['to']) ? '' : '-'.($filters['from'] ?? 'start').'-to-'.($filters['to'] ?? 'today');

        return Spreadsheet::make()
            ->sheet($customer->reg_no ?: 'Statement', [
                'Date', 'Time', 'Receipt No', 'Type', 'Payment Method', 'Collected By', 'Remarks', 'Deposit', 'Withdrawal', 'Balance',
            ], $rows)
            ->download("statement-{$customer->reg_no}{$dates}.xlsx");
    }

    /**
     * Every entry with the running balance after it, limited to the requested dates.
     *
     * @return array{filters: array, history: Collection, entries: list<Transaction>, opening: float, closing: float}
     */
    private function statement(Request $request, Customer $customer): array
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $history = $customer->transactions()
            ->with('collector:id,name')
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $running = 0;
        $opening = 0;
        $entries = [];
        foreach ($history as $transaction) {
            $running += $transaction->isDeposit() ? $transaction->amount : -$transaction->amount;
            $date = $transaction->date->toDateString();

            if (! empty($filters['from']) && $date < $filters['from']) {
                $opening = $running;

                continue;
            }
            if (! empty($filters['to']) && $date > $filters['to']) {
                continue;
            }

            $entries[] = $transaction->setAttribute('running_balance', round($running, 2));
        }

        return [
            'filters' => $filters,
            'history' => $history,
            'entries' => $entries,
            'opening' => round($opening, 2),
            'closing' => round($running, 2),
        ];
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate($this->rules($customer));
        $validated['reg_no'] = strtoupper($validated['reg_no'] ?? '') ?: $customer->reg_no ?: Customer::nextRegNo();

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        if ($customer->wallet_balance > 0) {
            return back()->with('error', "{$customer->full_name} still has ₦".number_format($customer->balance, 2).' saved. Pay it out before deleting.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    /**
     * Rebuild the stored balance from the customer's entries (fixes balances from older app versions).
     */
    public function recalculate(Customer $customer, Ledger $ledger)
    {
        [$previous, $current] = $ledger->rebuild($customer);

        return back()->with('success', 'Balance recalculated: ₦'.number_format($previous / 100, 2).' → ₦'.number_format(max($current, 0) / 100, 2).'.');
    }

    public function search(Request $request)
    {
        $customers = Customer::query()
            ->search($request->input('query'))
            ->orderBy('first_name')
            ->take(10)
            ->get(['id', 'reg_no', 'first_name', 'last_name', 'phone', 'wallet_balance']);

        return response()->json($customers);
    }

    /**
     * The next free customer ID, asked for fresh so it isn't stale if someone
     * else registered a customer after the form was opened.
     */
    public function nextId()
    {
        return response()->json(['reg_no' => Customer::nextRegNo()]);
    }

    private function rules(?Customer $customer = null): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer)->withoutTrashed()],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'reg_no' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'reg_no')->ignore($customer)],
        ];
    }
}
