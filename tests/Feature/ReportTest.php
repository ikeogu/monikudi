<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));

    $this->admin = User::factory()->create(['name' => 'Grace Admin']);
    $this->collector = User::factory()->collector()->create(['name' => 'Emeka']);
    $customer = Customer::factory()->create();
    $ledger = app(Ledger::class);

    $post = fn ($by, $type, $amount, $date, $method = 'cash') => $ledger->record($customer, [
        'type' => $type, 'amount' => $amount, 'date' => $date, 'payment_method' => $method, 'collected_by' => $by->id,
    ], $by);

    $post($this->collector, Transaction::TYPE_DEPOSIT, 1000, '2026-10-30');
    $post($this->admin, Transaction::TYPE_DEPOSIT, 500, '2026-10-30', 'transfer');
    $post($this->admin, Transaction::TYPE_WITHDRAWAL, 200, '2026-10-30');
    $post($this->collector, Transaction::TYPE_DEPOSIT, 2000, '2026-10-27'); // Tuesday, same week
    $post($this->collector, Transaction::TYPE_DEPOSIT, 4000, '2026-10-02'); // same month
    $post($this->collector, Transaction::TYPE_DEPOSIT, 8000, '2026-09-15'); // previous month
});

test('daily, weekly and monthly totals', function (string $period, int $deposits, int $count) {
    $this->actingAs($this->admin)->get(route('reports.index', ['period' => $period]))
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('summary.deposits', $deposits)
            ->where('summary.withdrawals', 200)
            ->where('summary.count', $count));
})->with([
    'daily' => ['daily', 1500, 3],
    'weekly' => ['weekly', 3500, 4],
    'monthly' => ['monthly', 7500, 5],
]);

test('reports break totals down by staff and payment method', function () {
    $this->actingAs($this->admin)->get(route('reports.index', ['period' => 'daily']))
        ->assertInertia(fn ($page) => $page
            ->where('byCollector.0.name', 'Emeka')
            ->where('byCollector.0.deposits', 1000)
            ->where('byMethod', fn ($rows) => collect($rows)->firstWhere('method', 'Bank Transfer')['deposits'] === 500)
            ->has('transactions', 3));
});

test('collectors only see their own collections in reports', function () {
    $this->actingAs($this->collector)->get(route('reports.index', ['period' => 'monthly']))
        ->assertInertia(fn ($page) => $page->where('summary.deposits', 7000)->where('summary.withdrawals', 0));
});

test('reports export as CSV', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.export', ['period' => 'monthly', 'date' => '2026-10-10']));

    $response->assertOk()->assertDownload('monthly-report-2026-10-01-to-2026-10-31.csv');
    $lines = array_filter(explode("\n", $response->streamedContent()));

    expect($lines)->toHaveCount(6) // header + 5 entries
        ->and($lines[0])->toStartWith('Date,Time,"Receipt No"');
});

test('the dashboard shows today\'s figures', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.todayDeposits', 1500)
            ->where('stats.todayWithdrawals', 200)
            ->where('stats.savingsBalance', 15300)
            ->has('chart.days', 31));
});
