<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // Posted by the ledger, never entered by hand: the first payment on each monthly page.
    public const TYPE_COMMISSION = 'commission';

    public const TYPE_LABELS = [
        self::TYPE_DEPOSIT => 'Deposit',
        self::TYPE_WITHDRAWAL => 'Withdrawal',
        self::TYPE_COMMISSION => 'Commission',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Bank Transfer',
        'pos' => 'POS',
    ];

    /** Column headings for exported entries, in toExportRow() order. */
    public const EXPORT_HEADINGS = [
        'Date', 'Time', 'Receipt No', 'Customer ID', 'Customer Name', 'Phone', 'Plan',
        'Type', 'Amount', 'Payment Method', 'Collected By', 'Remarks', 'Balance After',
    ];

    protected $fillable = [
        'customer_id',
        'savings_plan_id',
        'date',
        'type',
        'commission_exempt',
        'page_number',
        'page_day',
        'source_id',
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
            'commission_exempt' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SavingsPlan::class, 'savings_plan_id');
    }

    /** On a deposit: the commission entries for the pages its payment started. */
    public function pageCommissions(): HasMany
    {
        return $this->hasMany(self::class, 'source_id');
    }

    /** On a commission entry: the deposit that started the page. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
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

    public function isCommission(): bool
    {
        return $this->type === self::TYPE_COMMISSION;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
    }

    /**
     * The entry as one CSV/spreadsheet row; expects customer, plan and collector to be loaded.
     */
    public function toExportRow(): array
    {
        return [
            $this->date->toDateString(),
            $this->created_at?->format('H:i'),
            $this->receipt_no,
            $this->customer?->reg_no,
            $this->customer?->full_name,
            $this->customer?->phone,
            $this->plan?->name,
            $this->typeLabel(),
            $this->amount,
            self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method,
            $this->collector?->name,
            $this->description,
            $this->balance_after,
        ];
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

    public function scopeCommissions(Builder $query): void
    {
        $query->where('type', self::TYPE_COMMISSION);
    }

    /**
     * The order the ledger posts entries in: by business date, then as recorded.
     * A commission entry shares its deposit's timestamp, so it follows that deposit.
     */
    public function scopeInLedgerOrder(Builder $query, bool $descending = false): void
    {
        $direction = $descending ? 'desc' : 'asc';
        $query->orderBy('date', $direction)->orderBy('created_at', $direction)->orderBy('id', $direction);
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
