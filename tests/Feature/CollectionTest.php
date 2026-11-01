<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));

    $this->admin = User::factory()->create();
    $this->collector = User::factory()->collector()->create();
    $this->ada = Customer::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi']);
    $bayo = Customer::factory()->create();

    $post = fn ($customer, $by, $type, $amount, $date, $method = 'cash') => Transaction::factory()->create([
        'customer_id' => $customer->id, 'type' => $type, 'amount' => $amount, 'date' => $date,
        'payment_method' => $method, 'collected_by' => $by->id,
    ]);

    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 1000, '2026-10-30');
    $post($bayo, $this->admin, Transaction::TYPE_DEPOSIT, 500, '2026-10-30', 'transfer');
    $post($bayo, $this->admin, Transaction::TYPE_WITHDRAWAL, 200, '2026-10-30');
    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 1000, '2026-10-29');
    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 4000, '2026-10-02');
    $post($this->ada, $this->collector, Transaction::TYPE_DEPOSIT, 8000, '2026-09-15');
});

test('the month is summed day by day up to today, empty days included', function () {
    $this->actingAs($this->admin)->get(route('collections.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Collections/Index')
            ->where('filters.period', 'month')
            ->where('filters.view', 'totals')
            ->has('days', 30)
            ->where('days.0', fn ($day) => $day['date'] === '2026-10-01' && $day['count'] === 0)
            ->where('days.1.deposits', 4000)
            ->where('days.29.date', '2026-10-30')
            ->where('days.29.deposits', 1500)
            ->where('days.29.withdrawals', 200)
            ->where('days.29.net', 1300)
            ->where('days.29.count', 3)
            ->where('days.29.running', 6300)
            ->where('summary.deposits', 6500)
            ->where('summary.withdrawals', 200)
            ->where('summary.activeDays', 3)
            ->where('summary.totalDays', 30)
            ->where('entries', null));
});

test('a past month covers every day of that month', function () {
    $this->actingAs($this->admin)->get(route('collections.index', ['month' => '2026-09']))
        ->assertInertia(fn ($page) => $page
            ->has('days', 30)
            ->where('summary.deposits', 8000)
            ->where('step.previous', '2026-08')
            ->where('step.next', '2026-10'));
});

test('a custom range sums only the days in it', function () {
    $this->actingAs($this->admin)->get(route('collections.index', ['period' => 'range', 'from' => '2026-10-29', 'to' => '2026-10-30']))
        ->assertInertia(fn ($page) => $page
            ->has('days', 2)
            ->where('summary.deposits', 2500)
            ->where('step', null));
});

test('collections can be narrowed by customer, type, staff and payment method', function (array $filter, int $deposits, int $withdrawals) {
    $filter = array_map(fn ($value) => $value instanceof Closure ? $value() : $value, $filter);

    $this->actingAs($this->admin)->get(route('collections.index', $filter))
        ->assertInertia(fn ($page) => $page
            ->where('summary.deposits', $deposits)
            ->where('summary.withdrawals', $withdrawals));
})->with([
    'customer' => [['customer' => fn () => test()->ada->id], 6000, 0],
    'withdrawals only' => [['type' => Transaction::TYPE_WITHDRAWAL], 0, 200],
    'staff' => [['collector' => fn () => test()->collector->id], 6000, 0],
    'payment method' => [['method' => 'transfer'], 500, 0],
]);

test('filtering by customer shows the days they paid', function () {
    $this->actingAs($this->admin)->get(route('collections.index', ['customer' => $this->ada->id]))
        ->assertInertia(fn ($page) => $page
            ->where('customer.full_name', 'Ada Obi')
            ->where('summary.activeDays', 3)
            ->where('summary.customers', 1));
});

test('a day lists its entries newest first', function () {
    $this->actingAs($this->admin)->get(route('collections.index', ['period' => 'day', 'date' => '2026-10-30', 'view' => 'totals']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.view', 'list')
            ->has('days', 1)
            ->has('entries.data', 3)
            ->where('summary.customers', 2)
            ->where('step.next', null));
});

test('a month can be listed day by day', function () {
    $this->actingAs($this->admin)->get(route('collections.index', ['view' => 'list']))
        ->assertInertia(fn ($page) => $page
            ->where('entries.total', 5)
            ->where('entries.data.0.date', '2026-10-30')
            ->where('entries.data.4.date', '2026-10-02'));
});

test('collectors only see their own collections', function () {
    $this->actingAs($this->collector)->get(route('collections.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.deposits', 6000)
            ->where('summary.withdrawals', 0)
            ->where('collectors', []));
});

test('ranges must run forwards and stay within a year', function (array $range) {
    $this->actingAs($this->admin)->get(route('collections.index', ['period' => 'range', ...$range]))
        ->assertSessionHasErrors('to');
})->with([
    'backwards' => [['from' => '2026-10-30', 'to' => '2026-10-01']],
    'too long' => [['from' => '2025-01-01', 'to' => '2026-10-30']],
]);
