<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;

beforeEach(function () {
    $this->collector = User::factory()->collector()->create();
    $this->otherCollector = User::factory()->collector()->create();
    $this->customer = Customer::factory()->withBalance(1000)->create();
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('collectors cannot open admin areas', function (string $method, string $url) {
    $this->actingAs($this->collector)->{$method}($url)->assertForbidden();
})->with([
    ['get', '/staff'],
    ['get', '/staff/create'],
    ['get', '/settings'],
    ['put', '/settings'],
    ['get', '/backups'],
    ['post', '/backups'],
]);

test('collectors cannot edit or delete entries or customers', function () {
    $transaction = app(Ledger::class)->record($this->customer, [
        'type' => Transaction::TYPE_DEPOSIT, 'amount' => 100, 'date' => today()->toDateString(),
        'payment_method' => 'cash', 'collected_by' => $this->collector->id,
    ], $this->collector);

    $this->actingAs($this->collector);
    $this->get(route('transactions.edit', $transaction))->assertForbidden();
    $this->delete(route('transactions.destroy', $transaction))->assertForbidden();
    $this->delete(route('customers.destroy', $this->customer))->assertForbidden();
});

test('collectors can only record money they collected themselves', function () {
    $this->actingAs($this->collector)->post(route('transactions.store'), [
        'customer_id' => $this->customer->id,
        'type' => Transaction::TYPE_DEPOSIT,
        'amount' => 500,
        'date' => today()->toDateString(),
        'payment_method' => 'cash',
        'collected_by' => $this->otherCollector->id,
    ])->assertSessionHasErrors('collected_by');

    expect(Transaction::count())->toBe(0);
});

test('collectors only see their own collections in the transaction list', function () {
    $ledger = app(Ledger::class);
    foreach ([$this->collector, $this->otherCollector] as $collector) {
        $ledger->record($this->customer, [
            'type' => Transaction::TYPE_DEPOSIT, 'amount' => 100, 'date' => today()->toDateString(),
            'payment_method' => 'cash', 'collected_by' => $collector->id,
        ], $collector);
    }

    $this->actingAs($this->collector)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.collected_by', $this->collector->id)
            ->where('totals.deposits', 100));
});

test('deactivated staff cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('staff deactivated mid-session are signed out on their next request', function () {
    $this->actingAs($this->collector);
    $this->collector->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('logging in records the time', function () {
    $this->post('/login', ['email' => $this->collector->email, 'password' => 'password']);

    expect($this->collector->fresh()->last_login_at)->not->toBeNull();
});
