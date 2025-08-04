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


    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
