<?php

use App\Models\Customer;
use App\Models\SavingsPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(now()->setDate(2028, 3, 1)->setTime(9, 0));

    $this->admin = User::factory()->create();
    $this->ledger = app(Ledger::class);
    $this->customer = Customer::factory()->create();
    $this->plan = SavingsPlan::factory()->daily(500)->for($this->customer)->create(['name' => 'House rent']);
});

function pay(SavingsPlan $plan, float $amount, string $date, string $type = Transaction::TYPE_DEPOSIT, bool $close = false): Transaction
{
    return app(Ledger::class)->record($plan, [
        'type' => $type, 'amount' => $amount, 'date' => $date, 'payment_method' => 'cash', 'collected_by' => test()->admin->id,
    ], test()->admin, $close);
}

function commissions(SavingsPlan $plan)
{
    return $plan->transactions()->commissions()->inLedgerOrder()->get();
}

test('the first payment on a page is commission and the rest is the customer\'s', function () {
    foreach (range(1, 21) as $day) {
        $last = pay($this->plan, 500, sprintf('2026-10-%02d', $day));
    }

    $commissions = commissions($this->plan);
    expect($commissions)->toHaveCount(1)
        ->and($commissions[0]->amount)->toEqual(500)
        ->and($commissions[0]->source->date->toDateString())->toBe('2026-10-01')
        ->and($this->plan->fresh()->balance)->toEqual(10000)
        ->and($this->customer->fresh()->balance)->toEqual(10000)
        ->and([$last->page_number, $last->page_day])->toBe([1, 21]);
});

test('every new month starts a new page with its own commission', function () {
    pay($this->plan, 500, '2026-10-30');
    pay($this->plan, 500, '2026-10-31');
    $november = pay($this->plan, 500, '2026-11-01');

    expect(commissions($this->plan))->toHaveCount(2)
        ->and([$november->page_number, $november->page_day])->toBe([2, 1])
        ->and($this->plan->fresh()->balance)->toEqual(500);
});

test('a bigger first payment still gives up only one day', function () {
    pay($this->plan, 5000, '2026-10-05');

    expect(commissions($this->plan))->toHaveCount(1)
        ->and($this->plan->fresh()->balance)->toEqual(4500);
});

test('every page holds 31 days, whatever the month\'s length', function (string $first) {
    $full = pay($this->plan, 500 * 31, $first);
    $next = pay($this->plan, 500, $first);

    expect([$full->page_number, $full->page_day])->toBe([1, 31])
        ->and([$next->page_number, $next->page_day])->toBe([2, 1])
        ->and(commissions($this->plan))->toHaveCount(2)
        ->and($this->plan->currentPage()['days'])->toBe(31);
})->with([
    'October (31 days)' => ['2026-10-01'],
    'November (30 days)' => ['2026-11-01'],
    'February 2027 (28 days)' => ['2027-02-01'],
    'February 2028 (29 days)' => ['2028-02-01'],
]);

test('paying more than 31 days at once fills the page and opens another', function () {
    $deposit = pay($this->plan, 500 * 32, '2026-11-03');

    expect(commissions($this->plan)->pluck('page_number')->all())->toBe([1, 2])
        ->and([$deposit->page_number, $deposit->page_day])->toBe([2, 1])
        ->and($this->plan->fresh()->balance)->toEqual(15000);
});

test('collecting everything ends the page, so the next payment starts a new one', function () {
    pay($this->plan, 2500, '2026-10-01');
    pay($this->plan, 2000, '2026-10-05', Transaction::TYPE_WITHDRAWAL);
    pay($this->plan, 500, '2026-10-10');

    expect(commissions($this->plan))->toHaveCount(2)
        ->and($this->plan->fresh()->balance)->toEqual(0);
});

test('a partial withdrawal keeps the page going', function () {
    pay($this->plan, 2500, '2026-10-01');
    pay($this->plan, 1000, '2026-10-05', Transaction::TYPE_WITHDRAWAL);
    $deposit = pay($this->plan, 500, '2026-10-10');

    expect(commissions($this->plan))->toHaveCount(1)
        ->and($deposit->page_day)->toBe(6)
        ->and($this->plan->fresh()->balance)->toEqual(1500);
});

test('payments below the daily amount are rejected', function () {
    expect(fn () => pay($this->plan, 300, '2026-10-01'))->toThrow(ValidationException::class, 'The smallest payment on this plan is ₦500.00.');
    expect($this->plan->transactions()->count())->toBe(0);
});

test('withdrawals can take anything the customer has after commission', function () {
    pay($this->plan, 500, '2026-10-01');

    expect(fn () => pay($this->plan, 1, '2026-10-01', Transaction::TYPE_WITHDRAWAL))->toThrow(ValidationException::class, 'Insufficient balance');

    pay($this->plan, 1500, '2026-10-02');
    pay($this->plan, 1500, '2026-10-03', Transaction::TYPE_WITHDRAWAL);
    expect($this->plan->fresh()->balance)->toEqual(0);
});

test('a payout can close the plan, after which it takes no more entries', function () {
    pay($this->plan, 2500, '2026-10-01');
    pay($this->plan, 2000, '2026-10-05', Transaction::TYPE_WITHDRAWAL, close: true);

    expect($this->plan->fresh()->status)->toBe(SavingsPlan::STATUS_CLOSED);
    expect(fn () => pay($this->plan, 500, '2026-10-06'))->toThrow(ValidationException::class, 'This plan is closed');
});

test('commission follows edits and deletions of deposits', function () {
    $october = pay($this->plan, 500, '2026-10-31');
    $november = pay($this->plan, 500, '2026-11-01');
    expect(commissions($this->plan))->toHaveCount(2);

    // Moving the November payment back into October leaves one page.
    $this->ledger->update($november, ['date' => '2026-10-30']);
    $commissions = commissions($this->plan);
    expect($commissions)->toHaveCount(1)
        ->and($commissions[0]->source_id)->toBe($november->id)
        ->and($this->plan->fresh()->balance)->toEqual(500);

    // Deleting the deposit that started the page hands the page to the next one, keeping the receipt.
    $this->ledger->delete($november->fresh());
    $after = commissions($this->plan);
    expect($after)->toHaveCount(1)
        ->and($after[0]->id)->toBe($commissions[0]->id)
        ->and($after[0]->source_id)->toBe($october->id)
        ->and($this->plan->fresh()->balance)->toEqual(0);
});

test('commission entries cannot be edited or deleted by hand', function () {
    pay($this->plan, 500, '2026-10-01');
    $commission = commissions($this->plan)->first();

    expect(fn () => $this->ledger->update($commission, ['amount' => 100]))->toThrow(ValidationException::class);
    expect(fn () => $this->ledger->delete($commission))->toThrow(ValidationException::class);
});

test('a customer\'s balance is the sum of their plans', function () {
    $school = SavingsPlan::factory()->daily(1000)->for($this->customer)->create(['name' => 'School fees']);

    pay($this->plan, 1500, '2026-10-01');
    pay($school, 3000, '2026-10-01');

    expect($this->plan->fresh()->balance)->toEqual(1000)
        ->and($school->fresh()->balance)->toEqual(2000)
        ->and($this->customer->fresh()->balance)->toEqual(3000);
});

test('plans moved over from before take no commission until their daily amount is set', function () {
    $legacy = $this->customer->plans()->whereNull('daily_amount')->first();

    $old = pay($legacy, 700, '2026-10-01');
    expect(commissions($legacy))->toHaveCount(0)
        ->and($old->commission_exempt)->toBeTrue();

    $legacy->update(['daily_amount' => 500]);
    pay($legacy->fresh(), 500, '2026-10-02');

    expect(commissions($legacy))->toHaveCount(1)
        ->and($legacy->fresh()->balance)->toEqual(700);
});

test('customers without a plan are given one holding their balance', function () {
    // The stored ₦3,000 had drifted ₦500 above what the entries add up to.
    $customer = Customer::factory()->withBalance(3000)->create();
    $entry = Transaction::factory()->for($customer)->create(['amount' => 2500]);
    DB::table('transactions')->update(['savings_plan_id' => null]);
    DB::table('savings_plans')->where('customer_id', $customer->id)->delete();

    expect(SavingsPlan::adoptCustomersWithoutPlans())->toBe(1);

    $plan = $customer->plans()->sole();
    expect($plan->name)->toBe('General savings')
        ->and($plan->balance)->toEqual(3000)
        ->and($plan->openingKobo())->toBe(50000)
        ->and($plan->daily_amount)->toBeNull()
        ->and($entry->fresh()->savings_plan_id)->toBe($plan->id)
        ->and($entry->fresh()->commission_exempt)->toBeTrue();

    // New entries build on the stored balance rather than silently correcting it.
    pay($plan, 1000, '2026-10-01');
    expect($plan->fresh()->balance)->toEqual(4000);
});

test('pages show what was saved each month and what went to commission', function () {
    foreach (range(1, 21) as $day) {
        pay($this->plan, 500, sprintf('2026-10-%02d', $day));
    }
    pay($this->plan, 2500, '2026-11-02');

    expect($this->ledger->pages($this->plan))->toBe([
        ['number' => 2, 'month' => '2026-11', 'started_on' => '2026-11-02', 'days' => 5, 'capacity' => 31, 'saved' => 2500, 'commission' => 500, 'share' => 2000],
        ['number' => 1, 'month' => '2026-10', 'started_on' => '2026-10-01', 'days' => 21, 'capacity' => 31, 'saved' => 10500, 'commission' => 500, 'share' => 10000],
    ]);
});

test('the deposit form knows which page the next payment lands on', function () {
    expect($this->plan->currentPage())->toBeNull();

    pay($this->plan, 1500, '2026-11-04');
    expect($this->plan->currentPage())->toBe(['month' => '2026-11', 'number' => 1, 'day' => 3, 'days' => 31]);

    pay($this->plan, 1000, '2026-11-05', Transaction::TYPE_WITHDRAWAL);
    expect($this->plan->currentPage())->toBeNull();
});
