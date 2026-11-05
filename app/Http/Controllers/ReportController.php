<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Transaction;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    public const PERIODS = ['daily', 'weekly', 'monthly'];

    public function index(Request $request)
    {
        [$period, $from, $to] = $this->range($request);

        $transactions = Transaction::query()
            ->visibleTo($request->user())
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->with(['customer:id,reg_no,first_name,last_name', 'plan:id,name', 'collector:id,name'])
            ->inLedgerOrder()
            ->get();

        $perDay = $transactions->groupBy(fn ($t) => substr($t->date, 0, 10));
        $byDay = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $byDay[] = $this->totals($perDay->get($day->toDateString(), collect())) + ['date' => $day->toDateString()];
        }

        return Inertia::render('Reports/Index', [
            'period' => $period,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous' => $this->shift($period, $from, -1)->toDateString(),
                'next' => $to->lt(today()) ? $this->shift($period, $from, 1)->toDateString() : null,
            ],
            'summary' => $this->totals($transactions) + [
                'depositCount' => $transactions->where('type', Transaction::TYPE_DEPOSIT)->count(),
                'withdrawalCount' => $transactions->where('type', Transaction::TYPE_WITHDRAWAL)->count(),
                'commissionCount' => $transactions->where('type', Transaction::TYPE_COMMISSION)->count(),
                'customersServed' => $transactions->pluck('customer_id')->unique()->count(),
                'newCustomers' => Customer::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            ],
            'byDay' => $period === 'daily' ? [] : $byDay,
            'byCollector' => $transactions->groupBy(fn ($t) => $t->collector?->name ?? 'Unassigned')
                ->map(fn ($entries, $name) => $this->totals($entries) + ['name' => $name])
                ->sortByDesc('deposits')->values(),
            'byMethod' => $transactions->groupBy('payment_method')
                ->map(fn ($entries, $method) => $this->totals($entries) + ['method' => Transaction::PAYMENT_METHODS[$method] ?? ucfirst($method)])
                ->values(),
            'transactions' => $period === 'daily' ? $transactions->values() : [],
        ]);
    }

    /**
     * Every entry in the selected period as a CSV file, or an Excel file with format=xlsx.
     */
    public function export(Request $request)
    {
        [$period, $from, $to] = $this->range($request);

        $query = Transaction::query()
            ->visibleTo($request->user())
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->with(['customer', 'plan:id,name', 'collector:id,name'])
            ->inLedgerOrder();

        $filename = "{$period}-report-{$from->toDateString()}".($period === 'daily' ? '' : "-to-{$to->toDateString()}");

        if ($request->input('format') === 'xlsx') {
            return Spreadsheet::make()
                ->sheet('Entries', Transaction::EXPORT_HEADINGS, $query->lazy(500)->map->toExportRow())
                ->download("{$filename}.xlsx");
        }

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, Transaction::EXPORT_HEADINGS);

            foreach ($query->lazy(500) as $transaction) {
                fputcsv($out, array_map(
                    fn ($value) => is_float($value) ? number_format($value, 2, '.', '') : $value,
                    $transaction->toExportRow(),
                ));
            }

            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'date' => 'nullable|date',
        ]);

        $period = $validated['period'] ?? 'daily';
        $date = CarbonImmutable::parse($validated['date'] ?? today());

        return match ($period) {
            'daily' => [$period, $date->startOfDay(), $date->startOfDay()],
            'weekly' => [$period, $date->startOfWeek(), $date->endOfWeek()->startOfDay()],
            'monthly' => [$period, $date->startOfMonth(), $date->endOfMonth()->startOfDay()],
        };
    }

    private function shift(string $period, CarbonImmutable $from, int $direction): CarbonImmutable
    {
        return match ($period) {
            'daily' => $from->addDays($direction),
            'weekly' => $from->addWeeks($direction),
            'monthly' => $from->addMonths($direction),
        };
    }

    private function totals($entries): array
    {
        $deposits = (float) $entries->where('type', Transaction::TYPE_DEPOSIT)->sum('amount');
        $withdrawals = (float) $entries->where('type', Transaction::TYPE_WITHDRAWAL)->sum('amount');

        return [
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'commission' => (float) $entries->where('type', Transaction::TYPE_COMMISSION)->sum('amount'),
            // Cash taken in minus cash paid out; commission stays within what was taken in.
            'net' => $deposits - $withdrawals,
            'count' => $entries->count(),
        ];
    }
}
