<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public const PERIODS = ['day', 'month', 'range'];

    public const VIEWS = ['totals', 'list'];

    /** Longest custom range, so the daily table stays a sensible length. */
    public const MAX_DAYS = 366;

    /**
     * Collections summed day by day, or listed day by day, for a single day, a month
     * or a custom range. Collectors only see their own collections.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'from' => 'nullable|required_if:period,range|date',
            'to' => 'nullable|required_if:period,range|date|after_or_equal:from',
            'customer' => 'nullable|string',
            'type' => ['nullable', Rule::in([Transaction::TYPE_DEPOSIT, Transaction::TYPE_WITHDRAWAL])],
            'collector' => 'nullable|integer',
            'method' => ['nullable', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
        ]);

        $period = $filters['period'] ?? 'month';
        [$from, $to] = $this->range($period, $filters);
        // A single day only has one total, so it always shows its entries.
        $view = $period === 'day' ? 'list' : ($filters['view'] ?? 'totals');

        $query = Transaction::query()
            ->visibleTo($request->user())
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->when($filters['customer'] ?? null, fn ($query, $customer) => $query->where('customer_id', $customer))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['collector'] ?? null, fn ($query, $collector) => $query->where('collected_by', $collector))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method));

        $days = collect($this->dailyTotals(clone $query, $from, $to));
        $activeDays = $days->where('count', '>', 0)->count();

        return Inertia::render('Collections/Index', [
            'filters' => [
                'period' => $period,
                'view' => $view,
                'date' => $from->toDateString(),
                'month' => $from->format('Y-m'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer' => $filters['customer'] ?? '',
                'type' => $filters['type'] ?? '',
                'collector' => $filters['collector'] ?? '',
                'method' => $filters['method'] ?? '',
            ],
            'step' => $this->step($period, $from, $to),
            'today' => today()->toDateString(),
            'months' => $this->months($from),
            'days' => $days->values(),
            'summary' => [
                'deposits' => $days->sum('deposits'),
                'withdrawals' => $days->sum('withdrawals'),
                'net' => $days->sum('net'),
                'depositCount' => $days->sum('depositCount'),
                'withdrawalCount' => $days->sum('withdrawalCount'),
                'count' => $days->sum('count'),
                'customers' => (clone $query)->distinct()->count('customer_id'),
                'activeDays' => $activeDays,
                'totalDays' => $days->count(),
            ],
            'entries' => $view === 'list'
                ? (clone $query)
                    ->with(['customer:id,reg_no,first_name,last_name,phone', 'collector:id,name'])
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->paginate(50)
                    ->withQueryString()
                : null,
            'customer' => isset($filters['customer'])
                ? Customer::withTrashed()->find($filters['customer'], ['id', 'reg_no', 'first_name', 'last_name', 'phone', 'wallet_balance'])
                : null,
            'collectors' => $request->user()->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : [],
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $period, array $filters): array
    {
        if ($period === 'day') {
            $day = CarbonImmutable::parse($filters['date'] ?? today())->startOfDay();

            return [$day, $day];
        }

        if ($period === 'range') {
            $from = CarbonImmutable::parse($filters['from'])->startOfDay();
            $to = CarbonImmutable::parse($filters['to'])->startOfDay();

            if ($from->diffInDays($to) >= self::MAX_DAYS) {
                throw ValidationException::withMessages(['to' => 'Pick a range of a year or less.']);
            }

            return [$from, $to];
        }

        $month = CarbonImmutable::parse(($filters['month'] ?? today()->format('Y-m')).'-01');

        return [$month->startOfMonth(), $month->endOfMonth()->startOfDay()];
    }

    /**
     * One row per day up to today, including days with nothing collected,
     * with a running total of the net amount.
     */
    private function dailyTotals(Builder $query, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $query->selectRaw('date, type, count(*) as entries, sum(amount) as total')
            ->groupBy('date', 'type')
            ->get()
            ->groupBy(fn ($row) => substr($row->date, 0, 10));

        $last = $to->min(CarbonImmutable::today());
        $running = 0;
        $days = [];

        for ($day = $from; $day->lte($last); $day = $day->addDay()) {
            $entries = $rows->get($day->toDateString(), collect());
            $deposits = $entries->where('type', Transaction::TYPE_DEPOSIT);
            $withdrawals = $entries->where('type', Transaction::TYPE_WITHDRAWAL);
            $net = (float) $deposits->sum('total') - (float) $withdrawals->sum('total');
            $running += $net;

            $days[] = [
                'date' => $day->toDateString(),
                'deposits' => (float) $deposits->sum('total'),
                'withdrawals' => (float) $withdrawals->sum('total'),
                'net' => $net,
                'running' => $running,
                'depositCount' => (int) $deposits->sum('entries'),
                'withdrawalCount' => (int) $withdrawals->sum('entries'),
                'count' => (int) $entries->sum('entries'),
            ];
        }

        return $days;
    }

    /**
     * Where the previous/next arrows go for a day or a month; a custom range has none.
     */
    private function step(string $period, CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $hasNext = $to->lt(today());

        return match ($period) {
            'day' => ['previous' => $from->subDay()->toDateString(), 'next' => $hasNext ? $from->addDay()->toDateString() : null],
            'month' => ['previous' => $from->subMonth()->format('Y-m'), 'next' => $hasNext ? $from->addMonth()->format('Y-m') : null],
            default => null,
        };
    }

    /**
     * Month choices from the first recorded entry (or the month being viewed) to now, newest first.
     */
    private function months(CarbonImmutable $viewing): array
    {
        $first = Transaction::min('date');
        $month = ($first ? CarbonImmutable::parse($first)->min($viewing) : $viewing)->startOfMonth();
        $months = [];

        for (; $month->lte(today()); $month = $month->addMonth()) {
            $months[] = ['value' => $month->format('Y-m'), 'label' => $month->format('F Y')];
        }

        return array_reverse($months);
    }
}
