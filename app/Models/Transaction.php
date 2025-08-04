<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'date',
        'transaction_type',
        'payment_method',
        'amount',
        'description'

    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}