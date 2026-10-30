<?php

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;

function entry(Customer $customer, User $by, array $overrides = []): array
{
    return [
        'customer_id' => $customer->id,
        'type' => Transaction::TYPE_DEPOSIT,
        'amount' => 1000,
        'date' => today()->toDateString(),
        'payment_method' => 'cash',
        'collected_by' => $by->id,
        'description' => 'Daily contribution',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->customer = Customer::factory()->withBalance(500)->create();
});

test('a deposit increases the balance and records the balance on the entry', function () {
    $this->actingAs($this->admin)
        ->post(route('transactions.store'), entry($this->customer, $this->admin, ['amount' => 1250.50]))
        ->assertSessionHasNoErrors();

    $transaction = Transaction::sole();

    expect($this->customer->fresh()->balance)->toEqual(1750.50)
        ->and($transaction->balance_after)->toEqual(1750.50)
        ->and($transaction->created_by)->toBe($this->admin->id)
        ->and($transaction->collected_by)->toBe($this->admin->id);
});

test('saving an entry redirects to its receipt', function () {
    $response = $this->actingAs($this->admin)->post(route('transactions.store'), entry($this->customer, $this->admin));

    $response->assertRedirect(route('transactions.show', Transaction::sole()));
    $this->get(route('transactions.show', Transaction::sole()))->assertOk();
});

test('a withdrawal reduces the balance', function () {
    $this->actingAs($this->admin)
        ->post(route('transactions.store'), entry($this->customer, $this->admin, ['type' => Transaction::TYPE_WITHDRAWAL, 'amount' => 200]))
        ->assertSessionHasNoErrors();

    expect($this->customer->fresh()->balance)->toEqual(300);
});

test('a withdrawal larger than the balance is rejected and nothing is saved', function () {
    $this->actingAs($this->admin)
        ->post(route('transactions.store'), entry($this->customer, $this->admin, ['type' => Transaction::TYPE_WITHDRAWAL, 'amount' => 500.01]))
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0)
        ->and($this->customer->fresh()->balance)->toEqual(500);
});

test('future-dated entries are rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('transactions.store'), entry($this->customer, $this->admin, ['date' => today()->addDay()->toDateString()]))
        ->assertSessionHasErrors('date');
});

test('editing an entry adjusts the balance by the difference', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), entry($this->customer, $this->admin, ['amount' => 1000]));
    $transaction = Transaction::sole();

    $this->put(route('transactions.update', $transaction), [
        ...entry($this->customer, $this->admin, ['amount' => 400]),
    ])->assertSessionHasNoErrors();

    expect($this->customer->fresh()->balance)->toEqual(900)
        ->and($transaction->fresh()->amount)->toEqual(400)
        ->and($transaction->fresh()->balance_after)->toEqual(900);
});

test('changing a deposit into a withdrawal reverses it', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), entry($this->customer, $this->admin, ['amount' => 100]));

    $this->put(route('transactions.update', Transaction::sole()), entry($this->customer, $this->admin, [
        'type' => Transaction::TYPE_WITHDRAWAL,
        'amount' => 100,
    ]))->assertSessionHasNoErrors();

    expect($this->customer->fresh()->balance)->toEqual(400);
});

test('deleting an entry reverses it', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), entry($this->customer, $this->admin, ['amount' => 300]));

    $this->delete(route('transactions.destroy', Transaction::sole()))->assertRedirect(route('customers.show', $this->customer));

    expect($this->customer->fresh()->balance)->toEqual(500)
        ->and(Transaction::count())->toBe(0);
});

test('a deposit that was already spent cannot be deleted', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), entry($this->customer, $this->admin, ['amount' => 1000]));
    $deposit = Transaction::sole();
    $this->post(route('transactions.store'), entry($this->customer, $this->admin, ['type' => Transaction::TYPE_WITHDRAWAL, 'amount' => 1400]));

    $this->delete(route('transactions.destroy', $deposit))->assertSessionHasErrors('amount');

    expect($this->customer->fresh()->balance)->toEqual(100)
        ->and($deposit->fresh())->not->toBeNull();
});

test('ledger:rebuild fixes balances that drifted from the entries', function () {
    Transaction::factory()->for($this->customer)->create(['amount' => 700]);
    Transaction::factory()->for($this->customer)->withdrawal()->create(['amount' => 200]);

    $this->artisan('ledger:rebuild --dry-run')->assertSuccessful();
    expect($this->customer->fresh()->balance)->toEqual(500);

    $this->artisan('ledger:rebuild')->assertSuccessful();
    expect($this->customer->fresh()->balance)->toEqual(500)
        ->and(Transaction::where('type', Transaction::TYPE_WITHDRAWAL)->sole()->balance_after)->toEqual(500);
});
