<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SavingsPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Savings plans (goals) on a customer. Any staff member can open one; only admins edit or close them.
 */
class SavingsPlanController extends Controller
{
    /**
     * Rules shared with customer registration, which opens the first plan.
     */
    public static function amountRules(): array
    {
        return [
            'daily_amount' => 'required|numeric|min:1|max:10000000',
            'target_months' => 'nullable|integer|min:1|max:60',
        ];
    }

    public function create(Customer $customer)
    {
        return Inertia::render('Plans/Create', [
            'customer' => $customer->only('id', 'reg_no', 'full_name'),
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate(['name' => 'required|string|max:60', ...self::amountRules()]);

        $plan = $customer->plans()->create([...$validated, 'created_by' => $request->user()->id]);

        return redirect()->route('customers.show', $customer)->with('success', "{$plan->name} plan opened for {$customer->full_name}.");
    }

    public function edit(SavingsPlan $plan)
    {
        return Inertia::render('Plans/Edit', [
            'plan' => $plan->only('id', 'name', 'daily_amount', 'target_months', 'status'),
            'customer' => $plan->customer->only('id', 'reg_no', 'full_name'),
            'amountLocked' => $plan->daily_amount !== null && $plan->hasCountedDeposits(),
        ]);
    }

    public function update(Request $request, SavingsPlan $plan)
    {
        $validated = $request->validate(['name' => 'required|string|max:60', ...self::amountRules()]);

        // Past pages were counted in the old amount, so it can't change once a deposit has counted.
        $changed = (int) round($validated['daily_amount'] * 100) !== $plan->dailyKobo();
        if ($changed && $plan->daily_amount !== null && $plan->hasCountedDeposits()) {
            return back()->withErrors(['daily_amount' => 'Deposits have already counted toward pages at this amount. Close this plan and open a new one to change it.']);
        }

        $plan->update($validated);

        return redirect()->route('customers.show', $plan->customer_id)->with('success', "{$plan->name} plan updated.");
    }

    public function close(SavingsPlan $plan)
    {
        if ($plan->balanceKobo() > 0) {
            return back()->with('error', 'Pay out the ₦'.number_format($plan->balance, 2).' on this plan before closing it.');
        }

        $plan->close();

        return back()->with('success', "{$plan->name} plan closed.");
    }
}
