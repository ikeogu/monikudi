<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SavingsPlan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Every change to a balance goes through here, inside a DB transaction with the customer
 * and plan rows locked.
 *
 * Commission works in monthly pages. Each calendar month gets a page of 31 days, whatever
 * the month's length (SavingsPlan::PAGE_DAYS). The first payment on a page is the business's
 * commission: one day's amount. A new page starts when a plan gets its first counted
 * deposit, when a deposit falls in a new month, when the current page is full (the overflow
 * opens the next page), or after a withdrawal emptied the plan. After every change, settle()
 * replays the plan from its entries, so commissions, page numbers and balances can never
 * drift from the history.
 */
class Ledger
{
    /**
     * Post a deposit or withdrawal on a plan. A withdrawal that empties the plan ends its
     * page and, with $closePlan, closes the plan too. A plan that has reached its target
     * takes no more deposits and closes once it's paid out.
     */
    public function record(SavingsPlan $plan, array $data, User $by, bool $closePlan = false): Transaction
    {
        return DB::transaction(function () use ($plan, $data, $by, $closePlan) {
            $plan = $this->lock($plan);
            $deposit = ($data['type'] ?? null) === Transaction::TYPE_DEPOSIT;

            if ($plan->status === SavingsPlan::STATUS_CLOSED) {
                throw ValidationException::withMessages(['savings_plan_id' => 'This plan is closed. Open a new plan to keep saving.']);
            }
            if ($deposit && $plan->isCompleted()) {
                throw ValidationException::withMessages([
                    'savings_plan_id' => 'This plan has reached its target, so it takes no more deposits. Pay out the balance, or open a new plan to keep saving.',
                ]);
            }
            $this->ensureMinimum($plan, $data);
            $this->ensureWithinTarget($plan, $data);

            $transaction = $plan->transactions()->create([
                ...$data,
                'customer_id' => $plan->customer_id,
                'commission_exempt' => $plan->dailyKobo() === null,
                'created_by' => $by->id,
            ]);

            $this->settle($plan);

            if (! $deposit && $plan->balanceKobo() === 0 && ($closePlan || $plan->isCompleted())) {
                $plan->close();
            }

            return $transaction->refresh();
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->ensureEditable($transaction);
            $plan = $this->lock($transaction->plan);
            $this->ensureMinimum($plan, $data);

            $transaction->update($data);
            $this->settle($plan);

            return $transaction->refresh();
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->ensureEditable($transaction);
            $plan = $this->lock($transaction->plan);

            $transaction->delete();
            $this->settle($plan, overdrawnMessage: 'Deleting this entry would leave the plan with a negative balance.');
        });
    }

    /**
     * Trust the entries: drop any carried-over opening balance and replay every plan of a
     * customer from its entries alone. A dry run reports without saving.
     *
     * @return array{0: int, 1: int} [previous balance, recalculated balance] in kobo
     */
    public function rebuild(Customer $customer, bool $save = true): array
    {
        DB::beginTransaction();

        try {
            $customer = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            $previous = (int) $customer->wallet_balance;

            $calculated = 0;
            foreach ($customer->plans()->lockForUpdate()->get() as $plan) {
                $plan->forceFill(['opening_balance' => 0]);
                $calculated += $this->settle($plan, strict: false);
            }

            $save ? DB::commit() : DB::rollBack();

            return [$previous, $calculated];
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Replay one plan under the current rules (opening balance kept), e.g. after a rule changes.
     *
     * @return int the plan's balance in kobo
     */
    public function resettle(SavingsPlan $plan): int
    {
        return DB::transaction(fn () => $this->settle($this->lock($plan), strict: false));
    }

    /**
     * A plan's pages, newest first, for statements: what was saved on each and what went to commission.
     *
     * @return list<array{number: int, month: string, started_on: string, days: int, capacity: int, saved: float, commission: float, share: float}>
     */
    public function pages(SavingsPlan $plan): array
    {
        $daily = $plan->dailyKobo();
        if (! $daily) {
            return [];
        }

        $entries = $plan->transactions()->where('type', '!=', Transaction::TYPE_COMMISSION)->inLedgerOrder()->get();

        return collect($this->replay($plan, $entries, strict: false)['pages'])
            ->map(fn (array $page) => [
                'number' => $page['number'],
                'month' => $page['month'],
                'started_on' => $page['started_on'],
                'days' => min(intdiv($page['kobo'], $daily), $page['capacity']),
                'capacity' => $page['capacity'],
                'saved' => $page['kobo'] / 100,
                'commission' => $daily / 100,
                'share' => ($page['kobo'] - $daily) / 100,
            ])
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * Replay a plan, then make the stored commissions, page numbers and balances match.
     *
     * @return int the plan's recalculated balance in kobo (negative only when $strict is off)
     */
    private function settle(SavingsPlan $plan, bool $strict = true, ?string $overdrawnMessage = null): int
    {
        [$commissions, $entries] = $plan->transactions()->inLedgerOrder()->get()
            ->partition(fn (Transaction $transaction) => $transaction->isCommission());

        $result = $this->replay($plan, $entries->values(), $strict, $overdrawnMessage);

        foreach ($entries as $entry) {
            [$balance, $page, $day] = $result['snapshots'][$entry->id];
            $entry->balance_after = $balance / 100;
            $entry->page_number = $page;
            $entry->page_day = $day;
            if ($entry->isDirty()) {
                $entry->saveQuietly();
            }
        }

        // Keep existing commission entries (and their receipt numbers) where the page still exists.
        $existing = $commissions->groupBy('page_number');
        foreach ($result['commissions'] as $due) {
            $source = $due['source'];
            $commission = $existing->get($due['page'])?->shift() ?? new Transaction;

            $commission->fill([
                'customer_id' => $plan->customer_id,
                'savings_plan_id' => $plan->id,
                'type' => Transaction::TYPE_COMMISSION,
                'amount' => $plan->dailyKobo() / 100,
                'date' => $source->date->toDateString(),
                'payment_method' => $source->payment_method,
                'collected_by' => $source->collected_by,
                'created_by' => $source->created_by,
                'description' => 'Commission: '.CarbonImmutable::parse($due['month'].'-01')->format('F Y').' page',
                'source_id' => $source->id,
                'page_number' => $due['page'],
                'balance_after' => $due['balance'] / 100,
            ]);
            if (! $commission->exists) {
                $commission->created_at = $source->created_at;
            }
            if ($commission->isDirty()) {
                $commission->save();
            }
        }
        $existing->flatten(1)->each->delete();

        $plan->balance = max($result['balance'], 0) / 100;
        $plan->save();
        $plan->syncTargetStatus();

        $customer = Customer::withTrashed()->findOrFail($plan->customer_id);
        $customer->wallet_balance = (int) SavingsPlan::where('customer_id', $customer->id)->sum('balance');
        $customer->save();

        return $result['balance'];
    }

    /**
     * Walk a plan's deposits and withdrawals in ledger order, working out its pages, the
     * commission each new page costs and the balance after every entry.
     *
     * @param  Collection<int, Transaction>  $entries  deposits and withdrawals only
     * @return array{pages: array<int, array>, snapshots: array<string, array{0: int, 1: ?int, 2: ?int}>, commissions: list<array>, balance: int}
     */
    private function replay(SavingsPlan $plan, Collection $entries, bool $strict, ?string $overdrawnMessage = null): array
    {
        $daily = $plan->dailyKobo();
        $balance = $plan->openingKobo();
        $pages = [];
        $current = null;
        $emptied = false;
        $snapshots = [];
        $commissions = [];

        foreach ($entries as $entry) {
            $amount = (int) round($entry->amount * 100);

            if (! $entry->isDeposit()) {
                $balance -= $amount;
                if ($strict) {
                    $this->ensureNotOverdrawn($plan, $balance, $overdrawnMessage);
                }
                $snapshots[$entry->id] = [$balance, null, null];
                $emptied = $balance <= 0;

                continue;
            }

            $balance += $amount;
            $opened = [];

            if ($daily && ! $entry->commission_exempt) {
                $month = $entry->date->format('Y-m');
                $capacity = SavingsPlan::PAGE_DAYS * $daily;
                $left = $amount;

                while ($left > 0) {
                    $newPage = $current === null || $pages[$current]['month'] !== $month || $emptied;
                    $full = ! $newPage && $pages[$current]['kobo'] >= $capacity;

                    // Less than a day left over a full page isn't a day on a new page, so it
                    // stays on this one as extra savings rather than costing a whole commission.
                    if ($full && $left < $daily) {
                        $pages[$current]['kobo'] += $left;

                        break;
                    }

                    if ($newPage || $full) {
                        $current = count($pages) + 1;
                        $pages[$current] = [
                            'number' => $current,
                            'month' => $month,
                            'started_on' => $entry->date->toDateString(),
                            'kobo' => 0,
                            'capacity' => SavingsPlan::PAGE_DAYS,
                        ];
                        $opened[] = $current;
                        $emptied = false;
                    }

                    $take = min($left, $capacity - $pages[$current]['kobo']);
                    $pages[$current]['kobo'] += $take;
                    $left -= $take;
                }

                $snapshots[$entry->id] = [$balance, $current, min(intdiv($pages[$current]['kobo'], $daily), $pages[$current]['capacity'])];
            } else {
                $snapshots[$entry->id] = [$balance, null, null];
            }

            foreach ($opened as $number) {
                $balance -= $daily;
                if ($strict) {
                    $this->ensureNotOverdrawn($plan, $balance, $overdrawnMessage);
                }
                $commissions[] = ['page' => $number, 'month' => $pages[$number]['month'], 'source' => $entry, 'balance' => $balance];
            }
        }

        return ['pages' => $pages, 'snapshots' => $snapshots, 'commissions' => $commissions, 'balance' => $balance];
    }

    /**
     * Lock the customer, then the plan, in that order everywhere to avoid deadlocks.
     */
    private function lock(SavingsPlan $plan): SavingsPlan
    {
        Customer::withTrashed()->lockForUpdate()->findOrFail($plan->customer_id);

        return SavingsPlan::lockForUpdate()->findOrFail($plan->id);
    }

    private function ensureMinimum(SavingsPlan $plan, array $data): void
    {
        $daily = $plan->dailyKobo();

        if (($data['type'] ?? null) === Transaction::TYPE_DEPOSIT && $daily && (int) round($data['amount'] * 100) < $daily) {
            throw ValidationException::withMessages([
                'amount' => 'The smallest payment on this plan is ₦'.number_format($daily / 100, 2).'.',
            ]);
        }
    }

    /**
     * A plan with a target stops at it: a deposit may complete the target but not go past it.
     */
    private function ensureWithinTarget(SavingsPlan $plan, array $data): void
    {
        $target = $plan->targetDays();
        if (($data['type'] ?? null) !== Transaction::TYPE_DEPOSIT || ! $target) {
            return;
        }

        $daily = $plan->dailyKobo();
        $left = max($target - $plan->daysSaved(), 0) * $daily;

        if ((int) round($data['amount'] * 100) > $left) {
            $days = intdiv($left, $daily);
            throw ValidationException::withMessages([
                'amount' => 'Only ₦'.number_format($left / 100, 2)." ({$days} day".($days === 1 ? '' : 's').") completes this plan's "
                    ."{$plan->target_months}-month target. Put anything more on another plan.",
            ]);
        }
    }

    private function ensureEditable(Transaction $transaction): void
    {
        if ($transaction->isCommission()) {
            throw ValidationException::withMessages([
                'amount' => 'Commission is worked out from the deposits. Edit or delete the deposit instead.',
            ]);
        }
    }

    private function ensureNotOverdrawn(SavingsPlan $plan, int $balance, ?string $message = null): void
    {
        if ($balance >= 0) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => $message ?? 'Insufficient balance. Only ₦'.number_format($plan->balanceKobo() / 100, 2)
                ." is available on {$plan->name}.",
        ]);
    }
}
