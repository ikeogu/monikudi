<?php

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('new customers get the next ID for the configured prefix', function () {
    Customer::factory()->create(['reg_no' => 'AJ0000125']);
    Customer::factory()->create(['reg_no' => 'LEGACY-7']);

    $this->actingAs($this->admin)->post(route('customers.store'), [
        'first_name' => 'Chinedu', 'last_name' => 'Okafor', 'phone' => '08012345678',
    ])->assertSessionHasNoErrors();

    $customer = Customer::where('first_name', 'Chinedu')->sole();
    expect($customer->reg_no)->toBe('AJ0000126')
        ->and($customer->created_by)->toBe($this->admin->id);

    Setting::put(['customer_id_prefix' => 'GDC']);
    expect(Customer::nextRegNo())->toBe('GDC0000001');
});

test('the generate button gets the next free customer ID', function () {
    Customer::factory()->create(['reg_no' => 'AJ0000125']);

    $this->actingAs(User::factory()->collector()->create())
        ->getJson(route('customers.next-id'))
        ->assertOk()
        ->assertExactJson(['reg_no' => 'AJ0000126']);

    auth()->logout();
    $this->getJson(route('customers.next-id'))->assertUnauthorized();
});

test('a customer ID or phone number cannot be reused', function () {
    Customer::factory()->create(['reg_no' => 'AJ0000001', 'phone' => '08011111111']);

    $this->actingAs($this->admin)->post(route('customers.store'), [
        'first_name' => 'A', 'last_name' => 'B', 'reg_no' => 'aj0000001', 'phone' => '08011111111',
    ])->assertSessionHasErrors(['phone']);
});

test('customers can be found by name, full name, phone or ID', function (string $term) {
    Customer::factory()->create(['first_name' => 'Chinedu', 'last_name' => 'Okafor', 'phone' => '08012345678', 'reg_no' => 'AJ0000125']);
    Customer::factory()->create(['first_name' => 'Mary', 'last_name' => 'Nwosu', 'phone' => '08045678901', 'reg_no' => 'AJ0000128']);

    $this->actingAs($this->admin)->getJson(route('customers.search', ['query' => $term]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.reg_no', 'AJ0000125');
})->with(['Chinedu', 'okafor', 'Chinedu Okafor', '0801234', 'AJ0000125']);

test('the statement shows running balances, newest first', function () {
    $customer = Customer::factory()->create();
    $ledger = app(Ledger::class);
    $post = fn ($type, $amount, $date) => $ledger->record($customer, [
        'type' => $type, 'amount' => $amount, 'date' => $date, 'payment_method' => 'cash', 'collected_by' => $this->admin->id,
    ], $this->admin);

    $post(Transaction::TYPE_DEPOSIT, 1000, '2026-10-01');
    $post(Transaction::TYPE_DEPOSIT, 500, '2026-10-02');
    $post(Transaction::TYPE_WITHDRAWAL, 300, '2026-10-03');

    $this->actingAs($this->admin)->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Show')
            ->where('summary.deposits', 1500)
            ->where('summary.withdrawals', 300)
            ->where('summary.balance', 1200)
            ->where('transactions.0.running_balance', 1200)
            ->where('transactions.2.running_balance', 1000));

    $this->get(route('customers.show', [$customer, 'from' => '2026-10-02']))
        ->assertInertia(fn ($page) => $page
            ->where('summary.opening', 1000)
            ->has('transactions', 2));
});

test('a customer with money saved cannot be deleted', function () {
    $customer = Customer::factory()->withBalance(50)->create();

    $this->actingAs($this->admin)->delete(route('customers.destroy', $customer))->assertSessionHas('error');

    expect($customer->fresh()->trashed())->toBeFalse();
});

test('admins can recalculate a balance from its history', function () {
    $customer = Customer::factory()->withBalance(999)->create();
    Transaction::factory()->for($customer)->create(['amount' => 250]);

    $this->actingAs($this->admin)->post(route('customers.recalculate', $customer))->assertSessionHas('success');

    expect($customer->fresh()->balance)->toEqual(250);
});
