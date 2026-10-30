<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'reg_no',
        'phone',
        'email',
        'address',
        'created_by',
    ];

    protected $appends = ['full_name', 'balance'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * wallet_balance is stored in kobo; expose it in naira.
     */
    public function getBalanceAttribute()
    {
        return $this->wallet_balance / 100;
    }

    /**
     * Match on customer ID, phone number, first/last name or full name.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $like = "%{$term}%";

            $query->where('reg_no', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like);

            // "Chinedu Okafor" should match first + last name without a driver-specific CONCAT.
            $names = preg_split('/\s+/', $term, 2);
            if (count($names) === 2) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$names[0]}%")
                    ->where('last_name', 'like', "%{$names[1]}%"));
            }
        });
    }

    /**
     * Next customer ID for the prefix, e.g. AJ0000126 after AJ0000125.
     */
    public static function nextRegNo(?string $prefix = null): string
    {
        $prefix = strtoupper($prefix ?? Setting::get('customer_id_prefix'));

        $highest = static::withTrashed()
            ->where('reg_no', 'like', "{$prefix}%")
            ->pluck('reg_no')
            ->map(fn ($regNo) => substr($regNo, strlen($prefix)))
            ->filter(fn ($suffix) => ctype_digit($suffix))
            ->map(fn ($suffix) => (int) $suffix)
            ->max() ?? 0;

        return $prefix.str_pad($highest + 1, 7, '0', STR_PAD_LEFT);
    }
}
