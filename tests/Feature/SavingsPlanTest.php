<?php

use App\Models\Customer;
use App\Models\SavingsPlan;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(10, 0));

    $this->admin = User::factory()->create();
    $this->collector = User::factory()->collector()->create();
    $this->customer = Customer::factory()->create(['first_name' => 'Ada']);
    $this->plan = SavingsPlan::factory()->daily(500)->for($this->customer)->create(['name' => 'House rent']);
});

function deposit(SavingsPlan $plan, User $by, array $overrides = []): array
{
    return [
        'customer_id' => $plan->customer_id,
        'savings_plan_id' => $plan->id,
        'type' => Transaction::TYPE_DEPOSIT,
        'amount' => 500,
        'date' => today()->toDateString(),
        'payment_method' => 'cash',
        'collected_by' => $by->id,
        ...$overrides,
    ];
}

test('registering a customer opens their first plan', function () {
    $this->actingAs($this->collector)->post(route('customers.store'), [
        'first_name' => 'Bayo', 'last_name' => 'Ade', 'plan_name' => 'School fees', 'daily_amount' => 1000, 'target_months' => 3,
    ])->assertSessionHasNoErrors();

    $plan = Customer::where('first_name', 'Bayo')->sole()->plans()->sole();
    expect([$plan->name, $plan->daily_amount, $plan->target_months, $plan->created_by])
        ->toBe(['School fees', 1000, 3, $this->collector->id]);
});

test('a daily amount is required to register a customer', function () {
    $this->actingAs($this->admin)->post(route('customers.store'), ['first_name' => 'Bayo', 'last_name' => 'Ade'])
        ->assertSessionHasErrors('daily_amount');
});

test('a deposit goes onto the chosen plan and the receipt notes the commission', function () {
    $response = $this->actingAs($this->collector)->post(route('transactions.store'), deposit($this->plan, $this->collector, ['amount' => 1500]));

    $deposit = Transaction::deposits()->sole();
    $response->assertRedirect(route('transactions.show', $deposit))
        ->assertSessionHas('success', fn ($message) => str_contains($message, '₦500.00 of it is commission'));

    $this->get(route('transactions.show', $deposit))->assertInertia(fn ($page) => $page
        ->where('transaction.plan.name', 'House rent')
        ->where('transaction.page_number', 1)
        ->has('transaction.page_commissions', 1)
        ->where('transaction.page_commissions.0.amount', 500));
});

test('payments below the daily amount are rejected over HTTP', function () {
    $this->actingAs($this->collector)->post(route('transactions.store'), deposit($this->plan, $this->collector, ['amount' => 200]))
        ->assertSessionHasErrors('amount');
});

test('an entry must go onto one of the customer\'s own plans', function () {
    $other = SavingsPlan::factory()->daily(500)->create();

    $this->actingAs($this->admin)->post(route('transactions.store'), [
        ...deposit($this->plan, $this->admin),
        'savings_plan_id' => $other->id,
    ])->assertSessionHasErrors('savings_plan_id');
});

test('a full payout with close_plan closes the plan', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 2500]));
    $this->post(route('transactions.store'), deposit($this->plan, $this->admin, [
        'type' => Transaction::TYPE_WITHDRAWAL, 'amount' => 2000, 'close_plan' => true,
    ]))->assertSessionHasNoErrors();

    expect($this->plan->fresh()->status)->toBe(SavingsPlan::STATUS_CLOSED);
});

test('staff can open plans but only admins edit or close them', function () {
    $this->actingAs($this->collector)->post(route('plans.store', $this->customer), ['name' => 'School fees', 'daily_amount' => 1000])
        ->assertRedirect(route('customers.show', $this->customer));
    expect($this->customer->plans()->where('name', 'School fees')->exists())->toBeTrue();

    $this->get(route('plans.edit', $this->plan))->assertForbidden();
    $this->put(route('plans.update', $this->plan), ['name' => 'Rent', 'daily_amount' => 500])->assertForbidden();
    $this->post(route('plans.close', $this->plan))->assertForbidden();
});

test('the daily amount is fixed once a deposit has counted toward a page', function () {
    $this->actingAs($this->admin)->put(route('plans.update', $this->plan), ['name' => 'Rent', 'daily_amount' => 1000])
        ->assertSessionHasNoErrors();
    expect($this->plan->fresh()->daily_amount)->toEqual(1000);

    $this->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 1000]));

    $this->put(route('plans.update', $this->plan), ['name' => 'Rent', 'daily_amount' => 500])->assertSessionHasErrors('daily_amount');
    $this->put(route('plans.update', $this->plan), ['name' => 'House rent', 'daily_amount' => 1000, 'target_months' => 6])->assertSessionHasNoErrors();
    expect($this->plan->fresh()->target_months)->toBe(6);
});

test('a plan can only be closed once it is empty', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 1000]));

    $this->post(route('plans.close', $this->plan))->assertSessionHas('error');
    expect($this->plan->fresh()->isActive())->toBeTrue();

    $this->post(route('transactions.store'), deposit($this->plan, $this->admin, ['type' => Transaction::TYPE_WITHDRAWAL, 'amount' => 500]));
    $this->post(route('plans.close', $this->plan))->assertSessionHas('success');
    expect($this->plan->fresh()->isActive())->toBeFalse();
});

test('the customer page lists plans with their pages, and the statement can show one plan', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 2500]));

    $this->get(route('customers.show', $this->customer))->assertInertia(fn ($page) => $page
        ->has('plans', 2)
        ->where('plans.1.name', 'House rent')
        ->where('plans.1.commission', 500)
        ->where('plans.1.current_page', ['month' => '2026-11', 'number' => 1, 'day' => 5, 'days' => 31])
        ->where('plans.1.pages.0.share', 2000)
        ->where('summary.commission', 500)
        ->has('transactions', 2));

    $legacy = $this->customer->plans()->where('name', 'Daily savings')->sole();
    $this->get(route('customers.show', [$this->customer, 'plan' => $legacy->id]))
        ->assertInertia(fn ($page) => $page->has('transactions', 0)->where('summary.balance', 0));
});

test('the deposit form gets a customer\'s open plans', function () {
    $this->actingAs($this->collector)->getJson(route('customers.plans', $this->customer))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('1.name', 'House rent')
        ->assertJsonPath('1.daily_amount', 500);
});

test('admins see commission on the dashboard and plans that still need a daily amount', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 1500]));

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('stats.todayCommission', 500)
        ->where('stats.monthCommission', 500)
        ->where('plansNeedingAmount', 1));

    $this->actingAs($this->collector)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('stats.todayCommission', null)
        ->where('plansNeedingAmount', 0));

    $this->actingAs($this->admin)->get(route('customers.index', ['setup' => 1]))
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));
});

test('reports and daily collections total the commission', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin, ['amount' => 1500]));

    $this->get(route('reports.index', ['period' => 'daily']))->assertInertia(fn ($page) => $page
        ->where('summary.deposits', 1500)
        ->where('summary.commission', 500)
        ->where('summary.commissionCount', 1));

    $this->get(route('collections.index'))->assertInertia(fn ($page) => $page
        ->where('summary.commission', 500)
        ->where('days.4.commission', 500));

    $this->get(route('transactions.index', ['type' => Transaction::TYPE_COMMISSION]))
        ->assertInertia(fn ($page) => $page->has('transactions.data', 1)->where('totals.commission', 500));
});

test('commission entries cannot be opened for editing', function () {
    $this->actingAs($this->admin)->post(route('transactions.store'), deposit($this->plan, $this->admin));

    $this->get(route('transactions.edit', Transaction::commissions()->sole()))->assertRedirect()->assertSessionHas('error');
});
