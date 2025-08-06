<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'reg_no',
        'phone',
        'wallet_balance'
    ];

    protected $appends = ['full_name', 'wallet_balance_in_naira'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getWalletBalanceInNairaAttribute()
    {
        return number_format($this->wallet_balance / 100);
    }
}