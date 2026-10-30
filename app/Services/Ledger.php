<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a customer's balance goes through here, inside a DB transaction
 * with the customer row locked, so the stored balance always matches the entries.
 */
class Ledger
{
    public function record(Customer $customer, array $data, User $by): Transaction
    {
        return DB::transaction(function () use ($customer, $data, $by) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);

            $balance = $customer->wallet_balance + $this->signedKobo($data['type'], $data['amount']);
            $this->ensureNotOverdrawn($customer, $balance);

            $customer->wallet_balance = $balance;
            $customer->save();

            return $customer->transactions()->create([
                ...$data,
                'balance_after' => $balance / 100,
                'created_by' => $by->id,
            ]);
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $customer = Customer::withTrashed()->lockForUpdate()->findOrFail($transaction->customer_id);

            $delta = $this->signedKobo($data['type'], $data['amount'])
                - $this->signedKobo($transaction->type, $transaction->amount);
            $balance = $customer->wallet_balance + $delta;
            $this->ensureNotOverdrawn($customer, $balance);

            $customer->wallet_balance = $balance;
            $customer->save();

            if ($transaction->balance_after !== null) {
                $data['balance_after'] = $transaction->balance_after + $delta / 100;
            }
            $transaction->update($data);

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $customer = Customer::withTrashed()->lockForUpdate()->findOrFail($transaction->customer_id);

            $balance = $customer->wallet_balance - $this->signedKobo($transaction->type, $transaction->amount);
            $this->ensureNotOverdrawn($customer, $balance, 'Deleting this deposit would leave the customer with a negative balance.');

            $customer->wallet_balance = $balance;
            $customer->save();

            $transaction->delete();
        });
    }

    /**
     * Recompute a customer's balance and balance snapshots from their entries.
     *
     * @return array{0: int, 1: int} [previous balance, recalculated balance] in kobo
     */
    public function rebuild(Customer $customer, bool $save = true): array
    {
        return DB::transaction(function () use ($customer, $save) {
            $customer = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            $previous = (int) $customer->wallet_balance;
            $balance = 0;

            $transactions = $customer->transactions()->orderBy('date')->orderBy('created_at')->get();
            foreach ($transactions as $transaction) {
                $balance += $this->signedKobo($transaction->type, $transaction->amount);

                if ($save) {
                    $transaction->balance_after = $balance / 100;
                    $transaction->saveQuietly();
                }
            }

            if ($save) {
                $customer->wallet_balance = max($balance, 0);
                $customer->save();
            }

            return [$previous, $balance];
        });
    }

    private function signedKobo(string $type, float|string $amount): int
    {
        $kobo = (int) round($amount * 100);

        return $type === Transaction::TYPE_DEPOSIT ? $kobo : -$kobo;
    }

    private function ensureNotOverdrawn(Customer $customer, int $balance, ?string $message = null): void
    {
        if ($balance >= 0) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => $message ?? 'Insufficient balance. '.$customer->full_name.' has ₦'
                .number_format($customer->wallet_balance / 100, 2).' available.',
        ]);
    }
}
