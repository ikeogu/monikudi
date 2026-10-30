<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BackupManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

use function Illuminate\Support\defer;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BackupManager $backups)
    {
        $user = $request->user();
        $today = today()->toDateString();
        $visible = fn () => Transaction::query()->visibleTo($user);

        // Safety net for hosts without a scheduler: take the daily backup after this response is sent.
        if ($user->isAdmin()) {
            defer(fn () => $backups->createIfDue());
        }

        return Inertia::render('Dashboard', [
            'today' => $today,
            'stats' => [
                'customers' => Customer::count(),
                'savingsBalance' => $user->isAdmin() ? Customer::sum('wallet_balance') / 100 : null,
                'todayDeposits' => (float) $visible()->deposits()->where('date', $today)->sum('amount'),
                'todayDepositsCount' => $visible()->deposits()->where('date', $today)->count(),
                'todayWithdrawals' => (float) $visible()->withdrawals()->where('date', $today)->sum('amount'),
                'todayWithdrawalsCount' => $visible()->withdrawals()->where('date', $today)->count(),
                'totalDeposits' => (float) $visible()->deposits()->sum('amount'),
                'totalWithdrawals' => (float) $visible()->withdrawals()->sum('amount'),
                'activeStaff' => $user->isAdmin() ? User::active()->count() : null,
            ],
            'chart' => $this->monthChart($visible()),
            'recentTransactions' => $visible()
                ->with(['customer:id,reg_no,first_name,last_name,phone', 'collector:id,name'])
                ->latest()
                ->take(6)
                ->get(),
            'topCustomers' => $user->isAdmin()
                ? Customer::orderByDesc('wallet_balance')->take(5)->get(['id', 'reg_no', 'first_name', 'last_name', 'wallet_balance'])
                : [],
        ]);
    }

    /**
     * Daily deposit and withdrawal totals for the current month.
     */
    private function monthChart($query): array
    {
        $start = today()->startOfMonth();
        $end = today()->endOfMonth();

        $totals = $query->betweenDates($start->toDateString(), $end->toDateString())
            ->selectRaw('date, type, sum(amount) as total')
            ->groupBy('date', 'type')
            ->get()
            ->groupBy(fn ($row) => substr($row->date, 0, 10));

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $rows = $totals->get($day->toDateString(), collect());
            $days[] = [
                'date' => $day->toDateString(),
                'deposits' => (float) $rows->firstWhere('type', Transaction::TYPE_DEPOSIT)?->total,
                'withdrawals' => (float) $rows->firstWhere('type', Transaction::TYPE_WITHDRAWAL)?->total,
            ];
        }

        return ['month' => $start->format('F Y'), 'days' => $days];
    }
}
