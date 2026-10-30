<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Balances are only ever changed through App\Services\Ledger, never by saving a Transaction directly.
 */
class Transaction extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    // Stored values predate the deposit/withdrawal wording, so keep them and translate in the UI.
    public const TYPE_DEPOSIT = 'credit';

    public const TYPE_WITHDRAWAL = 'debit';

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Bank Transfer',
        'pos' => 'POS',
    ];

    protected $fillable = [
        'customer_id',
        'date',
        'type',
        'payment_method',
        'amount',
        'balance_after',
        'description',
        'collected_by',
        'created_by',
    ];

    protected $appends = ['receipt_no'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    /**
     * Customer balance (in naira) right after this entry was posted; stored in kobo.
     */
    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : $value / 100,
            set: fn ($value) => $value === null ? null : (int) round($value * 100),
        );
    }

    protected function receiptNo(): Attribute
    {
        return Attribute::get(fn () => $this->id ? 'RCP-'.strtoupper(substr($this->id, -8)) : null);
    }

    public function scopeDeposits(Builder $query): void
    {
        $query->where('type', self::TYPE_DEPOSIT);
    }

    public function scopeWithdrawals(Builder $query): void
    {
        $query->where('type', self::TYPE_WITHDRAWAL);
    }

    /**
     * Collectors only see the entries they collected; admins see everything.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('collected_by', $user->id);
        }
    }

    public function scopeBetweenDates(Builder $query, $from, $to): void
    {
        $query->whereBetween('date', [$from, $to]);
    }
}
