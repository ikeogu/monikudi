<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A savings goal with a fixed daily amount. Each calendar month is a page with as many
 * days as the month has; the first payment on a page is the business's commission.
 * The balance is kept by App\Services\Ledger.
 */
class SavingsPlan extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'customer_id',
        'name',
        'daily_amount',
        'target_months',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_months' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The payment per day in naira; stored in kobo. Empty on plans moved over from
     * before plans existed, which take no commission until it's set.
     */
    protected function dailyAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : $value / 100,
            set: fn ($value) => $value === null || $value === '' ? null : (int) round($value * 100),
        );
    }

    /**
     * Balance in naira; stored in kobo and only changed by the ledger.
     */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    public function dailyKobo(): ?int
    {
        $value = $this->attributes['daily_amount'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function balanceKobo(): int
    {
        return (int) ($this->attributes['balance'] ?? 0);
    }

    public function openingKobo(): int
    {
        return (int) ($this->attributes['opening_balance'] ?? 0);
    }

    /**
     * Whether any deposit has counted toward a page, after which the daily amount is fixed.
     */
    public function hasCountedDeposits(): bool
    {
        return $this->transactions()->deposits()->where('commission_exempt', false)->exists();
    }

    /**
     * The page the next deposit lands on, or null when it will start a new one
     * (no counted deposit yet, or the last withdrawal emptied the plan).
     *
     * @return array{month: string, number: int, day: int, days: int}|null
     */
    public function currentPage(): ?array
    {
        $last = $this->transactions()->where('type', '!=', Transaction::TYPE_COMMISSION)->inLedgerOrder(descending: true)->first();
        if (! $last || (! $last->isDeposit() && $last->balance_after <= 0)) {
            return null;
        }

        $deposit = $this->transactions()->deposits()->whereNotNull('page_number')->inLedgerOrder(descending: true)->first();
        if (! $deposit) {
            return null;
        }

        return [
            'month' => $deposit->date->format('Y-m'),
            'number' => $deposit->page_number,
            'day' => $deposit->page_day,
            'days' => $deposit->date->daysInMonth,
        ];
    }

    /**
     * What the deposit form needs to describe the next payment.
     */
    public function toFormArray(): array
    {
        return [
            ...$this->only('id', 'name', 'daily_amount', 'balance', 'target_months'),
            'current_page' => $this->currentPage(),
        ];
    }

    public function close(): void
    {
        $this->status = self::STATUS_CLOSED;
        $this->closed_at = now();
        $this->save();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Give each customer without a plan a "General savings" plan holding their current
     * balance and entries. Used when plans were introduced and after restoring an older
     * backup. Those entries never count toward a page. If the stored balance had drifted
     * from the entries, the difference is kept as the plan's opening balance.
     */
    public static function adoptCustomersWithoutPlans(): int
    {
        $adopted = 0;

        DB::table('customers')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('savings_plans')->whereColumn('savings_plans.customer_id', 'customers.id'))
            ->select(['id', 'wallet_balance', 'created_by'])
            ->chunkById(200, function ($customers) use (&$adopted) {
                foreach ($customers as $customer) {
                    $planId = strtolower((string) Str::ulid());
                    $fromEntries = (int) round(100 * (float) DB::table('transactions')
                        ->where('customer_id', $customer->id)
                        ->whereNull('deleted_at')
                        ->selectRaw("sum(case when type = 'credit' then amount else -amount end) as total")
                        ->value('total'));

                    DB::table('savings_plans')->insert([
                        'id' => $planId,
                        'customer_id' => $customer->id,
                        'name' => 'General savings',
                        'daily_amount' => null,
                        'balance' => $customer->wallet_balance,
                        'opening_balance' => $customer->wallet_balance - $fromEntries,
                        'status' => self::STATUS_ACTIVE,
                        'created_by' => $customer->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('transactions')
                        ->where('customer_id', $customer->id)
                        ->whereNull('savings_plan_id')
                        ->update(['savings_plan_id' => $planId, 'commission_exempt' => true]);

                    $adopted++;
                }
            });

        return $adopted;
    }
}
