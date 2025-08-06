<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $amount = Customer::sum('wallet_balance') / 100;
        return Inertia::render('Dashboard', [
            'customersCount' => \App\Models\Customer::count(),
            'transactionsCount' => \App\Models\Transaction::count(),
            'availableBalance' => number_format($amount)
        ]);
    }
}
